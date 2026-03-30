<?php
/**
 * Shared Helper for Order Item UI
 * PrintFlow - Neubrutalism Design System
 */

/**
 * Renders a single order item card in the Neubrutalism style.
 * Supports both cart items (session) and database items (order_items table).
 *
 * @param array $item The item data
 * @param bool $is_cart_item Whether this is from the session cart
 */
function render_order_item_neubrutalism($item, $is_cart_item = false, $show_price = true) {
    // 1. Data Normalization
    $custom = $is_cart_item ? ($item['customization'] ?? []) : json_decode($item['customization_data'] ?? '{}', true);
    $name = $item['name'] ?? ($item['product_name'] ?? null);

    // Dynamic naming for Sintra Board or generic names
    if (!empty($custom['sintra_type'])) {
        $name = 'Sintra Board - ' . $custom['sintra_type'];
    } elseif (empty($name) || in_array(strtolower(trim((string)$name)), ['custom order', 'customer order', 'service order', 'order item', 'sticker pack'])) {
        $name = get_service_name_from_customization($custom, $name ?: 'Order Item');
    }
    $name = normalize_service_name($name, 'Order Item');
    $category = $item['category'] ?? 'General';
    $unit_price = $is_cart_item ? $item['price'] : $item['unit_price'];
    $quantity = $item['quantity'];
    $subtotal = $unit_price * $quantity;
    
    // Design previews
    $design_url = null;
    $ref_url = null;
    
    if ($is_cart_item) {
        if (!empty($item['design_tmp_path']) && file_exists($item['design_tmp_path']) && !empty($item['design_mime'])) {
            $binary = @file_get_contents($item['design_tmp_path']);
            if ($binary) $design_url = 'data:' . $item['design_mime'] . ';base64,' . base64_encode($binary);
        }
        if (!empty($item['reference_tmp_path']) && file_exists($item['reference_tmp_path']) && !empty($item['reference_mime'])) {
            $binary = @file_get_contents($item['reference_tmp_path']);
            if ($binary) $ref_url = 'data:' . $item['reference_mime'] . ';base64,' . base64_encode($binary);
        }
    } else {
        $has_design = !empty($item['design_image']) || !empty($item['design_file']);
        if ($has_design) {
            $design_url = "/printflow/public/serve_design.php?type=order_item&id=" . (int)$item['order_item_id'];
        } else if (!empty($item['product_image'])) {
            $design_url = $item['product_image'];
        }

        if (!empty($item['reference_image_file'])) {
            $ref_url = "/printflow/public/serve_design.php?type=order_item&id=" . (int)$item['order_item_id'] . "&field=reference";
        }
    }

    // Fallback logic for design_url if still null
    if (!$design_url) {
        $cat_comb = strtolower(($item['category'] ?? '') . ' ' . ($name ?? ''));
        if (strpos($cat_comb, 't-shirt') !== false || strpos($cat_comb, 'tshirt') !== false) {
            $design_url = "/printflow/public/images/products/product_31.jpg";
        } else if (strpos($cat_comb, 'tarpaulin') !== false) {
            $design_url = "/printflow/public/images/products/product_42.jpg";
        } else if (strpos($cat_comb, 'reflectorized') !== false || strpos($cat_comb, 'signage') !== false || strpos($cat_comb, 'sticker') !== false || strpos($cat_comb, 'decal') !== false) {
            $design_url = "/printflow/public/images/products/product_21.jpg";
        }
    }

    // Field Map for Labels
    $field_map = [
        'size' => 'Size',
        'color' => 'Color',
        'shirt_color' => 'Color',
        'print_placement' => 'Placement',
        'design_type' => 'Design Type',
        'template' => 'Template',
        'width' => 'Width (ft)',
        'height' => 'Height (ft)',
        'finish' => 'Finish',
        'with_eyelets' => 'Eyelets',
        'shape' => 'Shape',
        'waterproof' => 'Waterproof',
        'Sintra_Type' => 'Sintraboard Type',
        'laminate_option' => 'Lamination Option',
        'lamination' => 'Lamination',
        'tshirt_provider' => 'T-Shirt Provider',
        'shirt_source' => 'Shirt Source',
        'Stand_Type' => 'Stand Type',
        'Cut_Type' => 'Cut Type',
        'Thickness' => 'Thickness',
        'Lamination' => 'Lamination Type',
        'needed_date' => 'Needed Date',
        'installation_fee' => 'Installation Fee',
    ];
    $skip = ['design_upload', 'reference_upload', 'notes', 'additional_notes', 'other_instructions', 'design_notes', 'Branch_ID', 'service_type', 'product_type', 'unit', 'install_province', 'install_city', 'install_barangay', 'install_street'];
    
    ?>
    <div style="border: 2px solid #000; background: #fff; margin-bottom: 2rem; overflow: hidden; box-shadow: 8px 8px 0px rgba(0,0,0,1);">
        <!-- Top Section: Core Info -->
        <div style="padding: 1.5rem; border-bottom: 2px solid #000; display: flex; gap: 1.5rem; align-items: flex-start;">
            <div style="width: 120px; height: 120px; border: 2px solid #000; border-radius: 8px; overflow: hidden; background: #f3f4f6; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <?php if ($design_url): ?>
                    <img src="<?php echo $design_url; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <span style="font-size: 2.5rem; color: #9ca3af; text-align: center;">Item</span>
                <?php endif; ?>
            </div>
            
            <div style="flex: 1; min-width: 0;">
                <div style="font-size: 1.5rem; font-weight: 900; margin-bottom: 0.25rem; word-wrap: break-word;"><?php echo htmlspecialchars($name); ?></div>
                <div style="font-size: 0.75rem; font-weight: 800; color: #6b7280; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 1rem; word-wrap: break-word;">
                    <?php echo htmlspecialchars($category); ?>
                </div>
                
                <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
                    <?php if ($show_price): ?>
                    <div style="min-width: 120px;">
                        <div style="font-size: 0.95rem; font-weight: 800;">Price: <?php echo format_currency($unit_price); ?></div>
                    </div>
                    <?php endif; ?>
                    <div style="min-width: 80px;">
                        <div style="font-size: 0.95rem; font-weight: 800;">Qty: <?php echo $quantity; ?></div>
                    </div>
                    <?php if ($show_price): ?>
                    <div style="min-width: 150px;">
                        <div style="font-size: 0.95rem; font-weight: 800;">Subtotal: <?php echo format_currency($subtotal); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Middle Section: Customization -->
        <div style="padding: 1.5rem; background: #fcfcfc;">
            <div style="font-size: 0.75rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; color: #000; display: flex; align-items: center; gap: 6px;">
                <span style="width: 8px; height: 8px; background: #000; border-radius: 50%;"></span>
                Specifications
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 1rem;">
                <?php 
                $has_specs = false;
                foreach ($custom as $ck => $cv): 
                    if (empty($cv) || in_array($ck, $skip) || strpos($ck, 'description') !== false) continue;
                    $has_specs = true;
                    $label = $field_map[$ck] ?? ucwords(str_replace(['_', '-'], ' ', $ck));
                    $display_val = ($ck === 'tshirt_provider' && $cv === 'shop') ? 'Shop will provide' : (($ck === 'tshirt_provider' && $cv === 'customer') ? 'Customer will provide' : (($ck === 'installation_fee' && is_numeric($cv)) ? format_currency((float)$cv) : $cv));
                ?>
                    <div style="border: 1px solid #000; padding: 0.75rem; border-radius: 6px; background: #fff; min-width: 0;">
                        <div style="font-size: 0.6rem; font-weight: 800; color: #6b7280; text-transform: uppercase; margin-bottom: 2px;"><?php echo $label; ?></div>
                        <div style="font-size: 0.9rem; font-weight: 800; color: #000; overflow-wrap: break-word; word-break: break-word;"><?php echo htmlspecialchars($display_val); ?></div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (!$has_specs): ?>
                    <div style="font-size: 0.8rem; color: #9ca3af; font-style: italic;">No specific customizations.</div>
                <?php endif; ?>
            </div>

            <!-- Notes -->
            <?php 
            $notes = $custom['notes'] ?? $custom['additional_notes'] ?? $custom['other_instructions'] ?? ($custom['design_description'] ?? ($custom['tshirt_design_description'] ?? ($custom['tarp_design_description'] ?? ($custom['design_notes'] ?? ($item['design_notes'] ?? null)))));
            if ($notes):
            ?>
                <div style="margin-top: 1rem; padding: 1rem; background: #fffbeb; border: 1px solid #000; border-radius: 8px; min-width: 0;">
                    <div style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: #92400e; margin-bottom: 4px;">Notes</div>
                    <div style="font-size: 0.9rem; font-weight: 700; color: #b45309; line-height: 1.4; overflow-wrap: break-word; word-break: break-word; white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($notes)); ?></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Reference -->
        <?php if ($ref_url): ?>
            <div style="padding: 1.25rem; background: #fff; border-top: 1px solid #000; border-style: dashed;">
                <div style="font-size: 0.75rem; font-weight: 900; text-transform: uppercase; margin-bottom: 0.75rem;">Reference Image</div>
                <div style="display: inline-block; padding: 6px; border: 2px solid #000; border-radius: 8px; background: white; box-shadow: 4px 4px 0px rgba(0,0,0,0.1);">
                    <img src="<?php echo $ref_url; ?>" style="max-width: 140px; border-radius: 4px; display: block;">
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Renders a single order item card in a clean, modern style.
 *
 * @param array $item The item data
 * @param bool $is_cart_item Whether this is from the session cart
 * @param bool $show_quantity Whether to show quantity in header
 */
function render_order_item_clean($item, $is_cart_item = false, $show_price = true, $show_quantity = true) {
    // 1. Data Normalization
    $custom = $is_cart_item ? ($item['customization'] ?? []) : json_decode($item['customization_data'] ?? '{}', true);
    $name = $item['name'] ?? ($item['product_name'] ?? 'Order Item');
    
    // Dynamic naming for Sintra Board or generic names
    if (!empty($custom['sintra_type'])) {
        $name = 'Sintra Board - ' . $custom['sintra_type'];
    } elseif (empty($name) || in_array(strtolower(trim((string)$name)), ['custom order', 'customer order', 'service order', 'order item', 'sticker pack'])) {
        $name = get_service_name_from_customization($custom, $name ?: 'Order Item');
    }
    $name = normalize_service_name($name, 'Order Item');
    
    $category = $item['category'] ?? 'General';
    $unit_price = $is_cart_item ? $item['price'] : $item['unit_price'];
    $quantity = $item['quantity'];
    $subtotal = $unit_price * $quantity;
    
    $design_url = null;
    $ref_url = null;
    
    if ($is_cart_item) {
        if (!empty($item['design_tmp_path']) && file_exists($item['design_tmp_path']) && !empty($item['design_mime'])) {
            $binary = @file_get_contents($item['design_tmp_path']);
            if ($binary) $design_url = 'data:' . $item['design_mime'] . ';base64,' . base64_encode($binary);
        }
        if (!empty($item['reference_tmp_path']) && file_exists($item['reference_tmp_path']) && !empty($item['reference_mime'])) {
            $binary = @file_get_contents($item['reference_tmp_path']);
            if ($binary) $ref_url = 'data:' . $item['reference_mime'] . ';base64,' . base64_encode($binary);
        }
    } else {
        $has_design = !empty($item['design_image']) || !empty($item['design_file']);
        if ($has_design) {
            $design_url = "/printflow/public/serve_design.php?type=order_item&id=" . (int)$item['order_item_id'];
        } else if (!empty($item['product_image'])) {
            $design_url = $item['product_image'];
        }

        if (!empty($item['reference_image_file'])) {
            $ref_url = "/printflow/public/serve_design.php?type=order_item&id=" . (int)$item['order_item_id'] . "&field=reference";
        }
    }

    // Fallback logic for design_url if still null
    if (!$design_url) {
        $cat_comb = strtolower(($item['category'] ?? '') . ' ' . ($name ?? ''));
        if (strpos($cat_comb, 't-shirt') !== false || strpos($cat_comb, 'tshirt') !== false) {
            $design_url = "/printflow/public/images/products/product_31.jpg";
        } else if (strpos($cat_comb, 'tarpaulin') !== false) {
            $design_url = "/printflow/public/images/products/product_42.jpg";
        } else if (strpos($cat_comb, 'reflectorized') !== false || strpos($cat_comb, 'signage') !== false || strpos($cat_comb, 'sticker') !== false || strpos($cat_comb, 'decal') !== false) {
            $design_url = "/printflow/public/images/products/product_21.jpg";
        }
    }

    $field_map = [
        'size' => 'Size',
        'color' => 'Color',
        'shirt_color' => 'Color',
        'print_placement' => 'Placement',
        'design_type' => 'Design Type',
        'template' => 'Template',
        'width' => 'Width (ft)',
        'height' => 'Height (ft)',
        'finish' => 'Finish',
        'with_eyelets' => 'Eyelets',
        'shape' => 'Shape',
        'waterproof' => 'Waterproof',
        'Sintra_Type' => 'Sintraboard Type',
        'laminate_option' => 'Lamination Option',
        'lamination' => 'Lamination',
        'tshirt_provider' => 'T-Shirt Provider',
        'shirt_source' => 'Shirt Source',
        'Stand_Type' => 'Stand Type',
        'Cut_Type' => 'Cut Type',
        'Thickness' => 'Thickness',
        'Lamination' => 'Lamination Type',
        'needed_date' => 'Needed Date',
        'installation_fee' => 'Installation Fee',
    ];
    $skip = ['design_upload', 'reference_upload', 'notes', 'additional_notes', 'other_instructions', 'design_notes', 'Branch_ID', 'service_type', 'product_type', 'unit', 'install_province', 'install_city', 'install_barangay', 'install_street'];
    ?>
    <div style="background: rgba(10, 37, 48, 0.48); backdrop-filter: blur(8px); padding: 0; overflow: hidden; border: 1px solid rgba(83, 197, 224, 0.24); border-radius: 16px; margin-bottom: 1.5rem; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <!-- Core Info -->
        <div style="padding: 1.25rem; display: flex; gap: 1.25rem; align-items: flex-start; border-bottom: 1px solid rgba(83, 197, 224, 0.15); background: rgba(255,255,255,0.02);">
            <div style="width: 130px; height: 130px; border-radius: 12px; overflow: hidden; background: rgba(0,0,0,0.25); border: 1px solid rgba(83, 197, 224, 0.2); display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: inset 0 2px 10px rgba(0,0,0,0.15);">
                <?php if ($design_url): ?>
                    <img src="<?php echo $design_url; ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease-in-out;" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'">
                <?php else: ?>
                    <span style="font-size: 2.2rem; color: rgba(255,255,255,0.15);">Item</span>
                <?php endif; ?>
            </div>
            
            <div style="flex: 1; min-width: 0;">
                <h3 style="font-size: 1.35rem; font-weight: 800; color: #eaf6fb; margin-bottom: 0.3rem; letter-spacing: -0.01em;"><?php echo htmlspecialchars($name); ?></h3>
                <div style="display: inline-flex; font-size: 0.72rem; font-weight: 700; color: #53c5e0; text-transform: uppercase; letter-spacing: 0.08em; padding: 3px 10px; border-radius: 20px; background: rgba(83, 197, 224, 0.12); border: 1px solid rgba(83, 197, 224, 0.18); margin-bottom: 1.25rem;">
                    <?php echo htmlspecialchars($category); ?>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 1rem;">
                    <?php if ($show_price): ?>
                    <div>
                        <div style="font-size: 0.68rem; color: #9fc4d4; font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Unit Price</div>
                        <div style="font-size: 1rem; color: #eaf6fb; font-weight: 700;"><?php echo format_currency($unit_price); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($show_quantity): ?>
                    <div>
                        <div style="font-size: 0.68rem; color: #9fc4d4; font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Quantity</div>
                        <div style="font-size: 1rem; color: #eaf6fb; font-weight: 700;"><?php echo $quantity; ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($show_price): ?>
                    <div>
                        <div style="font-size: 0.68rem; color: #53c5e0; font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Total</div>
                        <div style="font-size: 1rem; color: #53c5e0; font-weight: 800;"><?php echo format_currency($subtotal); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Specifications -->
        <div style="padding: 1.25rem; background: transparent;">
            <h4 style="font-size: 0.85rem; font-weight: 800; color: #eaf6fb; margin-bottom: 1rem; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid rgba(83, 197, 224, 0.12); padding-bottom: 0.5rem;">
                <svg style="width: 16px; height: 16px; color: #53c5e0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Order Specifications
            </h4>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 0.85rem;">
                <?php 
                $has_specs = false;
                foreach ($custom as $ck => $cv): 
                    if (empty($cv) || in_array($ck, $skip) || strpos($ck, 'description') !== false) continue;
                    $has_specs = true;
                    $label = $field_map[$ck] ?? ucwords(str_replace(['_', '-'], ' ', $ck));
                    $display_val = ($ck === 'tshirt_provider' && $cv === 'shop') ? 'Shop will provide' : (($ck === 'tshirt_provider' && $cv === 'customer') ? 'Customer will provide' : (($ck === 'installation_fee' && is_numeric($cv)) ? format_currency((float)$cv) : $cv));
                ?>
                    <div style="background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(83, 197, 224, 0.18); padding: 0.75rem 0.85rem; border-radius: 10px; transition: border-color 0.2s;">
                        <div style="font-size: 0.65rem; color: #9fc4d4; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.02em;"><?php echo $label; ?></div>
                        <div style="font-size: 0.95rem; font-weight: 700; color: #eaf6fb; overflow-wrap: break-word; word-break: break-word;"><?php echo htmlspecialchars($display_val); ?></div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (!$has_specs): ?>
                    <p style="font-size: 0.9rem; color: #9fc4d4; font-style: italic;">No specific customizations.</p>
                <?php endif; ?>
            </div>

            <!-- Notes -->
            <?php 
            $notes = $custom['notes'] ?? $custom['additional_notes'] ?? $custom['other_instructions'] ?? ($custom['design_description'] ?? ($custom['tshirt_design_description'] ?? ($custom['tarp_design_description'] ?? ($custom['design_notes'] ?? ($item['design_notes'] ?? null)))));
            if ($notes):
            ?>
                <div style="margin-top: 1.5rem; padding: 1.25rem; background: rgba(83, 197, 224, 0.08); border: 1px solid rgba(83, 197, 224, 0.22); border-left: 4px solid #53c5e0; border-radius: 12px;">
                    <div style="font-size: 0.75rem; font-weight: 800; color: #53c5e0; text-transform: uppercase; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                        Special Instructions & Notes
                    </div>
                    <div style="font-size: 0.95rem; color: #eaf6fb; line-height: 1.6; font-weight: 600; overflow-wrap: break-word; word-break: break-word; white-space: pre-wrap; transition: color 0.2s;"><?php echo nl2br(htmlspecialchars($notes)); ?></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Reference -->
        <?php if ($ref_url): ?>
            <div style="padding: 1.25rem; border-top: 1px solid rgba(83, 197, 224, 0.15); background: rgba(0,0,0,0.12);">
                <div style="font-size: 0.85rem; font-weight: 800; color: #eaf6fb; margin-bottom: 1rem; display: flex; align-items: center; gap: 8px;">
                    Reference Attachment
                </div>
                <div style="width: 140px; border-radius: 10px; overflow: hidden; border: 1px solid rgba(83, 197, 224, 0.24); padding: 5px; background: rgba(0,0,0,0.2);">
                    <img src="<?php echo $ref_url; ?>" style="width: 100%; height: auto; display: block; border-radius: 6px;">
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
