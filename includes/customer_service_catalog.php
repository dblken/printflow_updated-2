<?php
/**
 * Default copy shown in the customer “service detail” modal (editable per service in Admin).
 */
function printflow_default_customer_service_modal_text(): string {
    return 'Choose this service to start your customization. You will be able to select specific materials, sizes, and upload your layout on the next page to complete your order.';
}

/**
 * Default customer service tiles (used for DB seed + image/link fallback by service name).
 */
function printflow_default_customer_service_catalog(): array {
    return [
        ['name' => 'Tarpaulin', 'category' => 'Signage', 'img' => '/printflow/public/images/products/product_42.jpg', 'link' => 'order_tarpaulin.php'],
        ['name' => 'T-Shirt', 'category' => 'Apparel', 'img' => '/printflow/public/images/products/product_31.jpg', 'link' => 'order_tshirt.php'],
        ['name' => 'Stickers', 'category' => 'Decals', 'img' => '/printflow/public/images/products/product_21.jpg', 'link' => 'order_stickers.php'],
        ['name' => 'Glass/Wall', 'category' => 'Decals', 'img' => '/printflow/public/images/products/Glass Stickers  Wall  Frosted Stickers.png', 'link' => 'order_glass_stickers.php'],
        ['name' => 'Transparent', 'category' => 'Decals', 'img' => '/printflow/public/images/products/product_26.jpg', 'link' => 'order_transparent.php'],
        ['name' => 'Reflectorized', 'category' => 'Signage', 'img' => '/printflow/public/images/products/signage.jpg', 'link' => 'order_reflectorized.php'],
        ['name' => 'Sintraboard Standees', 'category' => 'Signage', 'img' => '/printflow/public/images/products/standeeflat.jpg', 'link' => 'order_sintraboard.php'],
        ['name' => 'Souvenirs', 'category' => 'Merchandise', 'img' => '/printflow/public/assets/images/services/default.png', 'link' => 'order_souvenirs.php'],
    ];
}
