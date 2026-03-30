/**
 * PrintFlow — unsaved-changes guard, save UX (overlay, spinner, toast).
 * Loaded from admin_style.php on dashboard shell pages.
 */
(function () {
    'use strict';

    function skipGlobal() {
        return document.body && document.body.hasAttribute('data-pf-skip-global-guard');
    }

    function isGuardedForm(form) {
        if (!form || form.method.toLowerCase() !== 'post') return false;
        if (form.hasAttribute('data-pf-skip-guard')) return false;
        if (form.hasAttribute('data-pf-autosave')) return false;
        if (form.closest('[data-pf-skip-guard]')) return false;
        if (form.hasAttribute('data-pf-guard') && form.getAttribute('data-pf-guard') === '0') return false;
        if (!document.body || !document.body.contains(form)) return false;
        /* All POST forms on pages that load this script (admin / staff / manager shell), unless opted out above */
        return true;
    }

    function cleanLabel(s) {
        if (s == null || s === '') return '';
        return String(s)
            .replace(/\s+/g, ' ')
            .replace(/[\u200B-\u200D\uFEFF]/g, '')
            .trim()
            .slice(0, 140);
    }

    function deriveFormLabel(form) {
        var el;
        var t = form.getAttribute('data-pf-form-label');
        if (t) return cleanLabel(t) || 'Unsaved section';

        el = form.querySelector('[data-pf-form-label]');
        if (el && el.getAttribute('data-pf-form-label')) {
            return cleanLabel(el.getAttribute('data-pf-form-label')) || 'Unsaved section';
        }

        el = form.closest('[data-pf-form-label]');
        if (el && el !== form && el.getAttribute('data-pf-form-label')) {
            return cleanLabel(el.getAttribute('data-pf-form-label')) || 'Unsaved section';
        }

        var card = form.closest('.settings-card');
        if (card && (el = card.querySelector('.settings-card-title'))) {
            t = cleanLabel(el.textContent);
            if (t) return t;
        }

        card = form.closest('.card, .chart-card, .kpi-card, .modal-panel, .modal-box, .modal-content');
        if (card) {
            el = card.querySelector(
                'h1, h2, h3, .card-title, .modal-title, .page-title, .dash-card-title, .settings-card-title'
            );
            if (el) {
                t = cleanLabel(el.textContent);
                if (t) return t;
            }
        }

        el = form.querySelector('legend');
        if (el) {
            t = cleanLabel(el.textContent);
            if (t) return t;
        }

        t = form.getAttribute('aria-label');
        if (t) return cleanLabel(t) || 'Unsaved section';

        var header = form.closest('header');
        if (header && (el = header.querySelector('h1, .page-title'))) {
            t = cleanLabel(el.textContent);
            if (t) return t + ' — form';
        }

        var btn = pickSubmitButton(form);
        if (btn) {
            if (btn.tagName === 'INPUT') t = cleanLabel(btn.value);
            else t = cleanLabel(btn.textContent);
            if (t) return t;
        }

        return 'Unsaved edits on this page';
    }

    function uniqueLabels(labels) {
        var seen = {};
        var out = [];
        for (var i = 0; i < labels.length; i++) {
            var L = labels[i];
            if (!seen[L]) {
                seen[L] = true;
                out.push(L);
            }
        }
        return out;
    }

    function getGuardedForms() {
        return [].slice.call(document.querySelectorAll('form')).filter(isGuardedForm);
    }

    function serializeFormState(form) {
        var parts = [];
        var els = form.querySelectorAll('input, select, textarea');
        for (var i = 0; i < els.length; i++) {
            var el = els[i];
            if (!el.name || el.disabled) continue;
            var t = (el.type || '').toLowerCase();
            if (t === 'submit' || t === 'button' || t === 'image') continue;
            if (t === 'checkbox' || t === 'radio') {
                parts.push(el.name + '=' + (el.checked ? (el.value || 'on') : ''));
                continue;
            }
            if (t === 'file') {
                var f = el.files;
                var seg = '';
                if (f && f.length) {
                    for (var j = 0; j < f.length; j++) {
                        seg += (j ? '|' : '') + f[j].name + ':' + f[j].size;
                    }
                }
                parts.push(el.name + '=' + seg);
                continue;
            }
            parts.push(el.name + '=' + (el.value == null ? '' : String(el.value)));
        }
        parts.sort();
        return parts.join('\n');
    }

    function capturePristine(form) {
        form.dataset.pfPristine = serializeFormState(form);
        form.classList.remove('pf-fg-form--dirty');
        refreshSubmitHighlight(form);
        updateDirtyHint(form);
    }

    function isFormDirty(form) {
        if (!form.dataset.pfPristine) return false;
        return serializeFormState(form) !== form.dataset.pfPristine;
    }

    function markDirty(form) {
        if (!isGuardedForm(form)) return;
        if (!form.dataset.pfPristine) {
            capturePristine(form);
            return;
        }
        var dirty = isFormDirty(form);
        form.classList.toggle('pf-fg-form--dirty', dirty);
        refreshSubmitHighlight(form);
        updateDirtyHint(form);
    }

    function refreshSubmitHighlight(form) {
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (btn) {
            btn.classList.toggle('pf-fg-save-highlight', form.classList.contains('pf-fg-form--dirty'));
        });
    }

    function updateDirtyHint(form) {
        var hint = form.querySelector('.pf-fg-dirty-hint');
        if (hint) {
            hint.hidden = !form.classList.contains('pf-fg-form--dirty');
        }
    }

    /** Row that holds primary actions (Save / Cancel) so the dirty hint can sit on the next line. */
    function findFormActionsRow(form, submitBtn) {
        var row = form.querySelector('.section-save');
        if (row) return row;
        if (!submitBtn || !submitBtn.parentElement) return null;
        var p = submitBtn.parentElement;
        try {
            var cs = window.getComputedStyle(p);
            if (cs.display === 'flex' || cs.display === 'inline-flex') return p;
        } catch (e) { /* ignore */ }
        return p;
    }

    function ensureDirtyHint(form) {
        if (form.querySelector('.pf-fg-dirty-hint')) return;
        var hint = document.createElement('span');
        hint.className = 'pf-fg-dirty-hint';
        hint.setAttribute('role', 'status');
        hint.textContent = 'You have unsaved changes';
        hint.hidden = true;
        var sub = pickSubmitButton(form);
        var row = findFormActionsRow(form, sub);
        if (row) {
            if (row.classList && row.classList.contains('section-save')) {
                if (row.style.display !== 'flex') {
                    row.style.display = 'flex';
                    row.style.alignItems = 'center';
                    row.style.gap = '8px';
                }
            }
            row.style.flexWrap = 'wrap';
            row.appendChild(hint);
        } else if (sub && sub.parentNode) {
            sub.parentNode.appendChild(hint);
        } else {
            form.appendChild(hint);
        }
    }

    function hasAnyDirty() {
        return getGuardedForms().some(function (f) {
            return f.classList.contains('pf-fg-form--dirty') && isFormDirty(f);
        });
    }

    function getDirtyForms() {
        return getGuardedForms().filter(function (f) {
            return f.classList.contains('pf-fg-form--dirty') && isFormDirty(f);
        });
    }

    function portalRoot() {
        return document.getElementById('pf-fg-portal') || document.body;
    }

    /* ---- DOM: overlay + modal + toast (in turbo-permanent portal when available) ---- */
    function ensureShell() {
        if (document.getElementById('pf-fg-save-overlay')) return;

        var root = portalRoot();

        var overlay = document.createElement('div');
        overlay.id = 'pf-fg-save-overlay';
        overlay.className = 'pf-fg-save-overlay';
        overlay.setAttribute('aria-hidden', 'true');
        root.appendChild(overlay);

        var wrap = document.createElement('div');
        wrap.innerHTML =
            '<div id="pf-fg-nav-modal" class="pf-fg-nav-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="pf-fg-nav-modal-title">' +
                '<div class="pf-fg-nav-modal__backdrop"></div>' +
                '<div class="pf-fg-nav-modal__panel">' +
                    '<h3 id="pf-fg-nav-modal-title" class="pf-fg-nav-modal__title">Unsaved Changes</h3>' +
                    '<p class="pf-fg-nav-modal__msg">You have unsaved changes. What would you like to do?</p>' +
                    '<p id="pf-fg-nav-modal-sub" class="pf-fg-nav-modal__sub" hidden>These areas are not saved yet:</p>' +
                    '<ul id="pf-fg-nav-modal-list" class="pf-fg-nav-modal__list" hidden></ul>' +
                    '<p id="pf-fg-nav-modal-err" class="pf-fg-nav-modal__err" hidden></p>' +
                    '<div class="pf-fg-nav-modal__actions">' +
                        '<button type="button" class="pf-fg-btn pf-fg-btn--neutral" id="pf-fg-nav-cancel">Cancel</button>' +
                        '<button type="button" class="pf-fg-btn pf-fg-btn--discard" id="pf-fg-nav-discard">Discard Changes</button>' +
                        '<button type="button" class="pf-fg-btn pf-fg-btn--accent" id="pf-fg-nav-save">Save Changes</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
        while (wrap.firstChild) {
            root.appendChild(wrap.firstChild);
        }

        var toast = document.createElement('div');
        toast.id = 'pf-fg-toast';
        toast.className = 'pf-fg-toast';
        toast.setAttribute('role', 'status');
        toast.hidden = true;
        root.appendChild(toast);

        bindNavModal();
        syncPortalAriaHidden();
    }

    var pendingNavigationUrl = null;
    var navModalOpen = false;
    var savingFromModal = false;

    /** Portal is aria-hidden in markup; when a dialog inside it is focused, ancestors must not stay hidden (a11y + Chrome warning). */
    function syncPortalAriaHidden() {
        var p = document.getElementById('pf-fg-portal');
        if (!p) return;
        var nav = document.getElementById('pf-fg-nav-modal');
        var navOpen = nav && nav.classList.contains('pf-fg-nav-modal--open');
        var ov = document.getElementById('pf-fg-save-overlay');
        var overlayOn = ov && ov.classList.contains('pf-fg-save-overlay--visible');
        var t = document.getElementById('pf-fg-toast');
        var toastOn = t && !t.hidden && t.classList.contains('pf-fg-toast--visible');
        var any = navOpen || overlayOn || toastOn;
        p.setAttribute('aria-hidden', any ? 'false' : 'true');
    }

    function openNavModal() {
        ensureShell();
        var modal = document.getElementById('pf-fg-nav-modal');
        var err = document.getElementById('pf-fg-nav-modal-err');
        var list = document.getElementById('pf-fg-nav-modal-list');
        var sub = document.getElementById('pf-fg-nav-modal-sub');
        err.hidden = true;
        err.textContent = '';

        var dirtyForms = getDirtyForms();
        var labels = uniqueLabels(dirtyForms.map(deriveFormLabel));
        list.innerHTML = '';
        for (var i = 0; i < labels.length; i++) {
            var li = document.createElement('li');
            li.textContent = labels[i];
            list.appendChild(li);
        }
        var showList = labels.length > 0;
        list.hidden = !showList;
        sub.hidden = !showList;

        modal.classList.add('pf-fg-nav-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        navModalOpen = true;
        syncPortalAriaHidden();
    }

    function closeNavModal() {
        var modal = document.getElementById('pf-fg-nav-modal');
        if (!modal) return;
        modal.classList.remove('pf-fg-nav-modal--open');
        modal.setAttribute('aria-hidden', 'true');
        navModalOpen = false;
        pendingNavigationUrl = null;
        syncPortalAriaHidden();
    }

    function pickSubmitButton(form) {
        var sec = form.querySelector('.section-save');
        var btn = sec
            ? sec.querySelector('button[type="submit"][name], input[type="submit"][name]')
            : null;
        if (!btn) {
            btn = form.querySelector('button[type="submit"][name], input[type="submit"][name]');
        }
        if (!btn) {
            btn = form.querySelector('button[type="submit"], input[type="submit"]');
        }
        return btn;
    }

    function saveFormViaFetch(form) {
        var fd = new FormData(form);
        var btn = pickSubmitButton(form);
        if (btn && btn.name) {
            fd.set(btn.name, btn.value || '');
        }
        var action = form.getAttribute('action') || window.location.href;
        return fetch(action, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: {
                Accept: 'text/html, application/xhtml+xml, text/vnd.turbo-stream.html, */*',
                'X-Requested-With': 'XMLHttpRequest',
            },
        }).then(function (res) {
            if (!res.ok) throw new Error('Save failed (' + res.status + ')');
            return res;
        });
    }

    function bindNavModal() {
        var modal = document.getElementById('pf-fg-nav-modal');
        if (!modal || modal.dataset.pfBound) return;
        modal.dataset.pfBound = '1';

        document.getElementById('pf-fg-nav-cancel').addEventListener('click', function () {
            closeNavModal();
        });

        document.getElementById('pf-fg-nav-discard').addEventListener('click', function () {
            var url = pendingNavigationUrl;
            getGuardedForms().forEach(capturePristine);
            closeNavModal();
            if (url) {
                if (window.Turbo && typeof window.Turbo.visit === 'function') {
                    window.Turbo.visit(url);
                } else {
                    window.location.href = url;
                }
            }
        });

        document.getElementById('pf-fg-nav-save').addEventListener('click', function () {
            var url = pendingNavigationUrl;
            var errEl = document.getElementById('pf-fg-nav-modal-err');
            var saveBtn = document.getElementById('pf-fg-nav-save');
            var dirtyList = getDirtyForms();
            if (!dirtyList.length) {
                closeNavModal();
                if (url) {
                    if (window.Turbo && typeof window.Turbo.visit === 'function') window.Turbo.visit(url);
                    else window.location.href = url;
                }
                return;
            }
            errEl.hidden = true;
            saveBtn.disabled = true;
            savingFromModal = true;
            window.__pfSavingAll = true;

            var chain = Promise.resolve();
            dirtyList.forEach(function (form) {
                chain = chain.then(function () {
                    return saveFormViaFetch(form);
                });
            });

            chain
                .then(function () {
                    dirtyList.forEach(capturePristine);
                    closeNavModal();
                    showSuccessToast();
                    savingFromModal = false;
                    window.__pfSavingAll = false;
                    saveBtn.disabled = false;
                    if (url) {
                        if (window.Turbo && typeof window.Turbo.visit === 'function') window.Turbo.visit(url);
                        else window.location.href = url;
                    }
                })
                .catch(function (e) {
                    errEl.textContent = (e && e.message) || 'Could not save. Please try again.';
                    errEl.hidden = false;
                    saveBtn.disabled = false;
                    savingFromModal = false;
                    window.__pfSavingAll = false;
                });
        });
    }

    /* ---- Save overlay + button state ---- */
    var activeSubmitBtn = null;
    var activeSubmitHtml = '';

    function setSaveOverlay(on) {
        ensureShell();
        var el = document.getElementById('pf-fg-save-overlay');
        if (!el) return;
        el.classList.toggle('pf-fg-save-overlay--visible', !!on);
        el.setAttribute('aria-hidden', on ? 'false' : 'true');
        syncPortalAriaHidden();
    }

    function setButtonSaving(btn, saving) {
        if (!btn) return;
        var isInput = btn.tagName === 'INPUT';
        if (saving) {
            if (btn.dataset.pfSaving === '1') return;
            btn.dataset.pfSaving = '1';
            activeSubmitBtn = btn;
            btn.disabled = true;
            if (isInput) {
                activeSubmitHtml = btn.value;
                btn.value = 'Saving…';
            } else {
                activeSubmitHtml = btn.innerHTML;
                btn.innerHTML =
                    '<span class="pf-fg-spinner" aria-hidden="true"></span><span>Saving…</span>';
            }
        } else {
            if (btn.dataset.pfSaving === '1') {
                btn.disabled = false;
                delete btn.dataset.pfSaving;
                if (isInput) {
                    btn.value = activeSubmitHtml;
                } else {
                    btn.innerHTML = activeSubmitHtml;
                }
            }
            activeSubmitBtn = null;
            activeSubmitHtml = '';
        }
    }

    function showSuccessToast() {
        ensureShell();
        var t = document.getElementById('pf-fg-toast');
        if (!t) return;
        t.textContent = 'Saved successfully';
        t.hidden = false;
        t.classList.add('pf-fg-toast--visible');
        syncPortalAriaHidden();
        clearTimeout(t._pfTimer);
        t._pfTimer = setTimeout(function () {
            t.classList.remove('pf-fg-toast--visible');
            t.hidden = true;
            syncPortalAriaHidden();
        }, 2600);
    }

    /* ---- Init ---- */
    function initForms() {
        if (skipGlobal()) return;
        ensureShell();
        getGuardedForms().forEach(function (form) {
            capturePristine(form);
            ensureDirtyHint(form);
        });
    }

    function onInput(e) {
        if (skipGlobal()) return;
        var t = e.target;
        if (!t || !t.form) return;
        markDirty(t.form);
    }

    /** Re-snapshot after Alpine / turbo-init (avoids false "dirty" when frameworks mutate the form after first paint). */
    function resyncAllGuardedPristine() {
        if (skipGlobal()) return;
        getGuardedForms().forEach(capturePristine);
    }

    function bindEvents() {
        if (skipGlobal()) return;
        document.addEventListener('input', onInput, true);
        document.addEventListener('change', onInput, true);

        /* Bubble phase so client-side validators on the form run first; avoid overlay + disabled submit when they preventDefault. */
        document.addEventListener('submit', function (e) {
            if (skipGlobal()) return;
            var form = e.target;
            if (!isGuardedForm(form)) return;
            if (e.defaultPrevented) return;
            if (form.dataset.pfSubmitLock === '1') {
                e.preventDefault();
                return;
            }
            form.dataset.pfSubmitLock = '1';
            var sub = e.submitter || pickSubmitButton(form);
            setSaveOverlay(true);
            setButtonSaving(sub, true);
        }, false);

        document.addEventListener('turbo:submit-end', function (e) {
            if (skipGlobal()) return;
            var fs = e.detail && e.detail.formSubmission;
            var form = fs && fs.formElement;
            if (!form || !isGuardedForm(form)) return;
            delete form.dataset.pfSubmitLock;
            setSaveOverlay(false);
            var sub = (fs && fs.submitter) || pickSubmitButton(form);
            setButtonSaving(sub, false);
            if (e.detail.success) {
                capturePristine(form);
                showSuccessToast();
            }
        });

        document.addEventListener('turbo:submit-start', function (e) {
            if (skipGlobal()) return;
            var fs = e.detail && e.detail.formSubmission;
            var form = fs && fs.formElement;
            if (!form || !isGuardedForm(form)) return;
            setSaveOverlay(true);
            setButtonSaving((fs && fs.submitter) || pickSubmitButton(form), true);
        });

        document.addEventListener('turbo:before-visit', function (e) {
            if (skipGlobal()) return;
            if (window.__pfSavingAll) return;
            if (!hasAnyDirty()) return;
            if (navModalOpen) return;
            e.preventDefault();
            pendingNavigationUrl = e.detail.url;
            openNavModal();
        });

        document.addEventListener(
            'click',
            function (ev) {
                if (skipGlobal()) return;
                if (window.__pfSavingAll) return;
                if (!hasAnyDirty()) return;
                if (navModalOpen) return;
                if (typeof Turbo === 'undefined') {
                    var a = ev.target.closest && ev.target.closest('a[href]');
                    if (!a || !a.href) return;
                    if (a.target === '_blank' || a.hasAttribute('download')) return;
                    var href = a.getAttribute('href') || '';
                    if (href.charAt(0) === '#') return;
                    var u;
                    try {
                        u = new URL(a.href, window.location.href);
                    } catch (err) {
                        return;
                    }
                    if (u.origin !== window.location.origin) return;
                    if (u.pathname === window.location.pathname && u.search === window.location.search && u.hash) return;
                    ev.preventDefault();
                    ev.stopPropagation();
                    pendingNavigationUrl = u.href;
                    openNavModal();
                }
            },
            true
        );
    }

    function boot() {
        if (skipGlobal()) return;
        bindEvents();
        initForms();
        queueMicrotask(function () {
            requestAnimationFrame(function () {
                resyncAllGuardedPristine();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    document.addEventListener('printflow:page-init', function () {
        resyncAllGuardedPristine();
    });

    document.addEventListener('turbo:load', function () {
        if (skipGlobal()) return;
        initForms();
        queueMicrotask(function () {
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    resyncAllGuardedPristine();
                });
            });
        });
    });

    window.printflowFormGuard = {
        markDirty: function (form) {
            markDirty(form);
        },
        capturePristine: function (form) {
            capturePristine(form);
        },
        refresh: function () {
            initForms();
            queueMicrotask(function () {
                requestAnimationFrame(resyncAllGuardedPristine);
            });
        },
    };
})();
