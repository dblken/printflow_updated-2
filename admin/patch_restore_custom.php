<?php
$file = __DIR__ . '/../includes/reports_analytics_scripts.php';
$raw  = file_get_contents($file);

// Find and replace the Customization Usage chart section
$startMarker = '(function initCustomUsageChart(){';
$startPos = strpos($raw, $startMarker);
if ($startPos === false) { die("Custom usage chart not found\n"); }

$endMarker = '})();';
$endPos = strpos($raw, $endMarker, $startPos + 100);
if ($endPos === false) { die("Custom usage chart end not found\n"); }
$endPos += strlen($endMarker);

$oldSection = substr($raw, $startPos, $endPos - $startPos);
echo "Found custom usage section (" . strlen($oldSection) . " bytes)\n";

$newSection = '<?php if (!empty($custom_usage)): ?>
    (function(){
        const prods = <?php echo json_encode(array_map(fn($c) => (string)($c[\'product\'] ?? \'\'), $custom_usage)); ?>;
        const cust  = <?php echo json_encode(array_map(fn($c) => (int)$c[\'custom_count\'], $custom_usage)); ?>;
        const tmpl  = <?php echo json_encode(array_map(fn($c) => (int)$c[\'template_count\'], $custom_usage)); ?>;
        
        const mount = document.getElementById(\'ch-custom\');
        if (!mount) return;
        
        const totals = prods.map((p, i) => cust[i] + tmpl[i]);
        const maxTotal = Math.max(...totals, 1);
        
        const totalCustom = cust.reduce((a, b) => a + b, 0);
        const totalTemplate = tmpl.reduce((a, b) => a + b, 0);
        const grandTotal = totalCustom + totalTemplate;
        const overallCustomPct = grandTotal > 0 ? Math.round((totalCustom / grandTotal) * 100) : 0;
        const overallTemplatePct = grandTotal > 0 ? Math.round((totalTemplate / grandTotal) * 100) : 0;
        
        let html = \'<div style="padding:20px;">\';
        
        html += \'<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid #e5e7eb;flex-wrap:wrap;gap:12px;">\';
        
        html += \'<div style="display:flex;gap:20px;font-size:11px;font-weight:600;color:#6b7280;font-family:inherit;">\';
        html += \'<span><span style="display:inline-block;width:10px;height:10px;background:#00232b;border-radius:2px;margin-right:5px;vertical-align:middle;"></span>Custom Upload</span>\';
        html += \'<span><span style="display:inline-block;width:10px;height:10px;background:#53C5E0;border-radius:2px;margin-right:5px;vertical-align:middle;"></span>Template / No Upload</span>\';
        html += \'</div>\';
        
        html += \'<div style="font-size:11px;font-weight:700;padding:4px 10px;border-radius:6px;white-space:nowrap;font-family:inherit;">\';
        if (overallCustomPct === 0) {
            html += \'<span style="color:#0e7490;background:#cffafe;">100% Template Usage</span>\';
        } else if (overallCustomPct === 100) {
            html += \'<span style="color:#0F4C5C;background:#E5EEF2;">100% Custom Upload</span>\';
        } else if (overallCustomPct > 50) {
            html += \'<span style="color:#0F4C5C;background:#E5EEF2;">Custom: \' + overallCustomPct + \'%</span>\';
        } else {
            html += \'<span style="color:#0e7490;background:#cffafe;">Template: \' + overallTemplatePct + \'%</span>\';
        }
        html += \'</div>\';
        
        html += \'</div>\';
        
        prods.forEach((prod, idx) => {
            const c = cust[idx];
            const t = tmpl[idx];
            const total = c + t;
            const custPct = total > 0 ? Math.round((c / total) * 100) : 0;
            const tmplPct = total > 0 ? Math.round((t / total) * 100) : 0;
            const barWidthPct = total > 0 ? (total / maxTotal) * 100 : 0;
            
            let barBg = \'\';
            if (c > 0 && t > 0) {
                barBg = `linear-gradient(to right, #00232b 0%, #00232b ${custPct}%, #53C5E0 ${custPct}%, #53C5E0 100%)`;
            } else if (c > 0) {
                barBg = \'#00232b\';
            } else if (t > 0) {
                barBg = \'#53C5E0\';
            } else {
                barBg = \'#e5e7eb\';
            }
            
            const displayName = prod.length > 36 ? prod.substring(0, 36) + \'...\' : prod;
            
            html += \'<div style="margin-bottom:14px;" class="pf-cu-row" data-idx="\' + idx + \'">\';
            
            html += \'<div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:6px;">\';
            html += \'<span style="font-size:13px;font-weight:600;color:#374151;font-family:inherit;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:70%;">\' + pfEscHtml(displayName) + \'</span>\';
            html += \'<span style="font-size:13px;font-weight:700;color:#111827;font-family:inherit;white-space:nowrap;margin-left:12px;font-variant-numeric:tabular-nums;">\' + total.toLocaleString() + \'</span>\';
            html += \'</div>\';
            
            html += \'<div style="position:relative;width:100%;height:28px;background:#f3f4f6;border-radius:6px;overflow:hidden;border:1px solid #e5e7eb;transition:all 0.2s ease;cursor:pointer;" class="pf-cu-bar">\';
            
            if (total > 0) {
                html += \'<div style="position:absolute;left:0;top:0;height:100%;width:\' + barWidthPct + \'%;background:\' + barBg + \';transition:width 0.8s cubic-bezier(0.4, 0, 0.2, 1);border-radius:5px;">\';
                html += \'</div>\';
            } else {
                html += \'<span style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);font-size:11px;color:#9ca3af;font-style:italic;font-family:inherit;">No usage</span>\';
            }
            
            html += \'</div>\';
            html += \'</div>\';
        });
        
        html += \'</div>\';
        
        mount.innerHTML = html;
        
        let tooltip = document.getElementById(\'pf-cu-tooltip\');
        if (!tooltip) {
            tooltip = document.createElement(\'div\');
            tooltip.id = \'pf-cu-tooltip\';
            tooltip.style.cssText = \'position:fixed;z-index:9999;pointer-events:none;visibility:hidden;opacity:0;background:#1e293b;color:#fff;padding:10px 14px;border-radius:8px;font-size:12px;box-shadow:0 10px 25px rgba(0,0,0,0.2);transition:opacity 0.15s ease,visibility 0.15s ease;border:1px solid #334155;min-width:220px;max-width:320px;font-family:inherit;\';
            document.body.appendChild(tooltip);
        }
        
        const rows = mount.querySelectorAll(\'.pf-cu-row\');
        rows.forEach((row, idx) => {
            const bar = row.querySelector(\'.pf-cu-bar\');
            
            row.addEventListener(\'mouseenter\', function(e) {
                if (bar) {
                    bar.style.transform = \'translateY(-1px)\';
                    bar.style.boxShadow = \'0 4px 12px rgba(0,0,0,0.1)\';
                    bar.style.filter = \'brightness(1.05)\';
                }
                
                const c = cust[idx];
                const t = tmpl[idx];
                const total = c + t;
                const custPct = total > 0 ? ((c / total) * 100).toFixed(1) : \'0.0\';
                const tmplPct = total > 0 ? ((t / total) * 100).toFixed(1) : \'0.0\';
                
                let tooltipHTML = \'<div style="font-weight:800;color:#f8fafc;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid rgba(255,255,255,0.1);font-family:inherit;">\' + pfEscHtml(prods[idx]) + \'</div>\';
                
                if (total > 0) {
                    tooltipHTML += \'<div style="display:flex;justify-content:space-between;margin-bottom:4px;font-family:inherit;">\';
                    tooltipHTML += \'<span style="color:#cbd5e1;">Custom Upload:</span>\';
                    tooltipHTML += \'<span style="color:#fff;font-weight:700;">\' + c.toLocaleString() + \' <span style="color:#94a3b8;">(\' + custPct + \'%)</span></span>\';
                    tooltipHTML += \'</div>\';
                    
                    tooltipHTML += \'<div style="display:flex;justify-content:space-between;margin-bottom:8px;font-family:inherit;">\';
                    tooltipHTML += \'<span style="color:#cbd5e1;">Template / No Upload:</span>\';
                    tooltipHTML += \'<span style="color:#fff;font-weight:700;">\' + t.toLocaleString() + \' <span style="color:#94a3b8;">(\' + tmplPct + \'%)</span></span>\';
                    tooltipHTML += \'</div>\';
                    
                    tooltipHTML += \'<div style="padding-top:6px;border-top:1px solid rgba(255,255,255,0.1);display:flex;justify-content:space-between;font-family:inherit;">\';
                    tooltipHTML += \'<span style="color:#94a3b8;font-size:11px;">Total Units:</span>\';
                    tooltipHTML += \'<span style="color:#53C5E0;font-weight:800;">\' + total.toLocaleString() + \'</span>\';
                    tooltipHTML += \'</div>\';
                } else {
                    tooltipHTML += \'<div style="color:#94a3b8;font-style:italic;font-size:11px;font-family:inherit;">No customization usage data yet</div>\';
                }
                
                tooltip.innerHTML = tooltipHTML;
                tooltip.style.visibility = \'visible\';
                tooltip.style.opacity = \'1\';
            });
            
            row.addEventListener(\'mousemove\', function(e) {
                let x = e.clientX + 15;
                let y = e.clientY + 15;
                
                const tooltipRect = tooltip.getBoundingClientRect();
                const winW = window.innerWidth;
                const winH = window.innerHeight;
                
                if (x + tooltipRect.width > winW) {
                    x = e.clientX - tooltipRect.width - 15;
                }
                if (y + tooltipRect.height > winH) {
                    y = e.clientY - tooltipRect.height - 15;
                }
                
                tooltip.style.left = x + \'px\';
                tooltip.style.top = y + \'px\';
            });
            
            row.addEventListener(\'mouseleave\', function() {
                if (bar) {
                    bar.style.transform = \'translateY(0)\';
                    bar.style.boxShadow = \'none\';
                    bar.style.filter = \'brightness(1)\';
                }
                
                tooltip.style.visibility = \'hidden\';
                tooltip.style.opacity = \'0\';
            });
        });
        
        const box = mount.closest(\'.ch-box\');
        if (box) {
            box.classList.remove(\'pf-chart-loading\');
            box.removeAttribute(\'aria-busy\');
            box.classList.add(\'pf-chart-reveal-done\');
        }
    })();';

$raw = str_replace($oldSection, $newSection, $raw);
file_put_contents($file, $raw);
echo "DONE - Restored Customization Usage chart\n";