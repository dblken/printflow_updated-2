<?php
/**
 * Service order detail modal — same shell, width (560px), and block order as
 * staff/customizations.php “Customization Details” modal.
 */
?>
<div x-show="showSvcModal" x-cloak>
    <div class="modal-overlay" @click.self="closeSvcModal()">
        <div class="modal-panel" @click.stop>

            <div x-show="svcLoading" style="padding:48px;text-align:center;">
                <div style="width:40px;height:40px;border:3px solid #e5e7eb;border-top-color:#06A1A1;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 12px;"></div>
                <p style="color:#6b7280;font-size:14px;">Loading service order…</p>
            </div>

            <div x-show="!svcLoading && svcErr" style="padding:32px;">
                <p style="color:#b91c1c;font-size:14px;" x-text="svcErr"></p>
                <button type="button" class="btn-secondary mt-4" @click="closeSvcModal()">Close</button>
            </div>

            <template x-if="!svcLoading && !svcErr && svc && svc.id">
            <div>

                <div style="padding:20px 24px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <h3 style="font-size:18px;font-weight:700;color:#1f2937;margin:0;" x-text="'Service Order #' + svc.id"></h3>
                        <p style="font-size:12px;color:#6b7280;margin:2px 0 0;" x-text="svc.service_name"></p>
                    </div>
                    <button type="button" @click="closeSvcModal()" style="background:transparent;border:none;cursor:pointer;color:#6b7280;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div style="padding:24px;">

                    <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;padding-bottom:20px;border-bottom:1px solid #f3f4f6;">
                        <div x-show="!svc.customer_profile_picture || svc.customer_profile_picture === 'null' || svc.customer_profile_picture === 'undefined'" style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#06A1A1,#047676);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:22px;flex-shrink:0;" x-text="svc.customer_initial"></div>
                        <img x-show="svc.customer_profile_picture && svc.customer_profile_picture !== 'null' && svc.customer_profile_picture !== 'undefined'" :src="getProfileImage(svc.customer_profile_picture)" style="width:56px;height:56px;border-radius:50%;object-fit:cover;border:2px solid #06A1A1;background:#f3f4f6;flex-shrink:0;" onerror="this.src='/printflow/public/assets/uploads/profiles/default.png'">
                        <div>
                            <div style="font-size:16px;font-weight:700;color:#1f2937;" x-text="svc.customer_full_name || 'Customer'"></div>
                            <div style="display:flex;align-items:center;gap:8px;margin-top:4px;">
                                <span style="font-size:11px; font-weight:500;" class="status-pill" :class="svc.cust_badge_class" x-text="svc.customer_type"></span>
                                <span style="font-size:12px;color:#6b7280;" x-text="svc.customer_contact"></span>
                            </div>
                            <div x-show="svc.show_email_row" style="font-size:12px;color:#6b7280;margin-top:4px;" x-text="svc.customer_email"></div>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                        <div>
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:4px;">Service</label>
                            <div style="font-size:13px;color:#1f2937;font-weight:500;" x-text="svc.service_name"></div>
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:4px;">Status</label>
                            <span class="status-pill" :class="svc.status_pill_class" x-text="svc.status"></span>
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:4px;">Dimensions</label>
                            <div style="font-size:13px;color:#1f2937;" x-text="svc.dimensions_display || '—'"></div>
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:4px;">Quantity</label>
                            <div style="font-size:13px;color:#1f2937;" x-text="svc.qty_display || '—'"></div>
                        </div>
                        <div x-show="!svc.pending_like">
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:4px;">Estimated Total</label>
                            <div style="font-size:13px;color:#1f2937;font-weight:400;" x-text="svc.formatted_total"></div>
                        </div>
                        <div x-show="!svc.pending_like">
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:4px;">Amount Paid</label>
                            <div style="font-size:13px;color:#1f2937;font-weight:400;" x-text="svc.amount_paid_display"></div>
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:4px;">Priority</label>
                            <div style="font-size:13px;font-weight:600;" :style="svc.priority_is_high ? 'color:#ef4444' : 'color:#1f2937'" x-text="svc.priority_val || '—'"></div>
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:4px;">Due Date</label>
                            <div style="font-size:13px;color:#1f2937;" :style="svc.due_overdue ? 'color:#ef4444;' : ''" x-text="svc.due_val || 'Not set'"></div>
                        </div>
                    </div>

                    <div style="margin-bottom:20px; padding:16px; border-radius:12px; border:1px solid #e5e7eb; background:#f9fafb;">
                        <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:12px;">Order Details (Customer Specifications)</label>
                        <template x-if="!svc.spec_rows || svc.spec_rows.length === 0">
                            <p style="font-size:13px;color:#6b7280;margin:0;font-style:italic;">No specifications recorded.</p>
                        </template>
                        <template x-if="svc.spec_rows && svc.spec_rows.length > 0">
                            <div style="margin-bottom:16px; padding:12px; background:#fff; border:1px solid #e5e7eb; border-radius:8px;">
                                <div style="font-size:13px; font-weight:700; color:#1f2937; margin-bottom:10px;" x-text="svc.svc_line_title"></div>
                                <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(140px, 1fr)); gap:10px;">
                                    <template x-for="(row, idx) in svc.spec_rows" :key="row.field_name + '-' + idx">
                                        <div style="padding:8px; border:1px solid #e5e7eb; border-radius:6px; background:#fff; min-width:0; overflow-wrap:break-word;">
                                            <div style="font-size:10px; font-weight:600; color:#6b7280; text-transform:uppercase; margin-bottom:2px;" x-text="row.label"></div>
                                            <div style="font-size:12px; font-weight:500; color:#1f2937; word-break:break-word; overflow-wrap:break-word;" x-text="row.field_value"></div>
                                            <button type="button" x-show="row.placement_url" @click="svcOpenPlacement(row.placement_url, row.field_value)" style="margin-top:8px;width:100%;font-size:12px;font-weight:600;padding:6px 12px;border-radius:8px;border:1px solid #c7d2fe;background:#eef2ff;color:#4338ca;cursor:pointer;">View placement diagram</button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div style="margin-bottom:20px;">
                        <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:6px;">Production Notes</label>
                        <div style="font-size:13px;color:#6b7280;background:#fffbeb;border:1px solid #fef3c7;padding:10px 14px;border-radius:8px;font-style:italic;word-break:break-word;overflow-wrap:break-word;white-space:pre-wrap;" x-text="svc.notes_plain && svc.notes_plain.length ? svc.notes_plain : 'No instructions provided.'"></div>
                    </div>

                    <div x-show="svc.files && svc.files.length > 0" style="margin-top:16px;">
                        <template x-for="(f, fi) in svc.files" :key="'f-' + fi">
                            <div style="margin-bottom:16px;">
                                <div style="font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:6px;">Design Preview</div>
                                <div style="display:flex; align-items:flex-end; gap:12px;">
                                    <template x-if="f.is_image && f.preview_url">
                                        <a :href="f.open_url" target="_blank" rel="noopener">
                                            <img :src="f.preview_url" alt="" style="width:140px; height:auto; border-radius:10px; border:1px solid #e2e8f0; cursor:zoom-in; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);" @error="$el.src = svc.file_icon_fallback">
                                        </a>
                                    </template>
                                    <template x-if="f.is_image && f.preview_url">
                                        <a :href="f.open_url" target="_blank" rel="noopener" style="font-size:11px; color:#4f46e5; text-decoration:none; font-weight:600; padding:6px 10px; background:#f5f3ff; border-radius:6px; transition:all 0.2s;" onmouseover="this.style.background='#ddd6fe'" onmouseout="this.style.background='#f5f3ff'">Open Original →</a>
                                    </template>
                                    <template x-if="(!f.is_image || !f.preview_url) && f.open_url">
                                        <a :href="f.open_url" target="_blank" rel="noopener" style="display:block;padding:16px;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;text-decoration:none;color:#1f2937;">📄 <span x-text="f.name"></span></a>
                                    </template>
                                    <template x-if="!f.open_url">
                                        <div style="padding:16px;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;color:#9ca3af;font-size:13px;">No file data available</div>
                                    </template>
                                </div>
                                <p style="font-size:14px;color:#6b7280;margin-top:4px;" x-text="f.name"></p>
                            </div>
                        </template>
                    </div>
                </div>

                <div style="padding:16px 24px;border-top:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center;gap:8px;">
                    <div style="display:flex;gap:8px; flex-wrap:wrap; align-items:center;">
                        <div x-show="svc.show_approve_block" style="display:flex; gap:8px;">
                            <button type="button" @click="svcApprove()" class="btn-action indigo" style="padding:6px 12px; font-weight:600;">Approve & Start Production</button>
                            <button type="button" @click="svcReject()" class="btn-action" style="padding:6px 12px; color:#ef4444; background:#fef2f2; border:1px solid #fee2e2; font-weight:600;">Request Revision</button>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;flex-shrink:0;">
                        <button type="button" @click="closeSvcModal()" class="btn-secondary">Close</button>
                    </div>
                </div>
            </div>
            </template>
        </div>
    </div>
</div>

<div x-show="placementOpen" x-cloak style="position:fixed;inset:0;z-index:10050;display:flex;align-items:center;justify-content:center;padding:24px;background:rgba(15,23,42,0.65);" @click.self="closePlacement()">
    <div style="position:relative;background:#fff;border-radius:16px;max-width:min(520px,100%);max-height:90vh;overflow:auto;padding:20px 20px 16px;box-shadow:0 25px 50px rgba(0,0,0,0.25);" @click.stop>
        <button type="button" @click="closePlacement()" style="position:absolute;top:12px;right:12px;width:36px;height:36px;border:none;border-radius:50%;background:#f1f5f9;color:#64748b;font-size:22px;line-height:1;cursor:pointer;" aria-label="Close">&times;</button>
        <h3 style="margin:0 0 12px;font-size:16px;font-weight:700;color:#0f172a;padding-right:36px;" x-text="placementTitle || 'Print placement'"></h3>
        <img x-show="placementSrc" :src="placementSrc" alt="" style="display:block;width:100%;height:auto;border-radius:12px;border:1px solid #e5e7eb;">
    </div>
</div>
