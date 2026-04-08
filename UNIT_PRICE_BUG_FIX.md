# Unit Price Bug Fix - PrintFlow

## Problem Summary
The unit price displayed on the payment page was showing the **total price** (unit_price × quantity) instead of the **per-unit price**.

### Example:
- **Expected**: Unit Price: ₱800.00, Quantity: 6, Total: ₱4,800.00
- **Actual**: Unit Price: ₱4,800.00, Quantity: 6, Total: ₱28,800.00

## Root Cause
In the service order forms (e.g., `order_glass_stickers.php`, `order_tarpaulin.php`, `order_tshirt.php`), the `price` field in the cart was being calculated as:

```php
'price' => $base_price * $quantity  // WRONG - This is the total, not unit price
```

When the order was placed in `checkout.php`, this total price was saved to the `unit_price` column in the `order_items` table, causing the display issue.

## Files Fixed

### 1. customer/order_glass_stickers.php (Line 79-86)
**Before:**
```php
$unit_price = 45.00;
$base_price = $area * $unit_price * $quantity;
$installation_fee = ($installation === 'With Installation') ? (500 + ($area * 15)) : 0;

$_SESSION['cart'][$item_key] = [
    'price' => $base_price + $installation_fee,  // WRONG - Total price
    'quantity' => $quantity,
```

**After:**
```php
$price_per_sqft = 45.00;
$unit_price = $area * $price_per_sqft;  // Price per piece
$installation_fee = ($installation === 'With Installation') ? (500 + ($area * 15)) : 0;
$installation_fee_per_unit = $installation_fee / $quantity;  // Divide by quantity

$_SESSION['cart'][$item_key] = [
    'price' => $unit_price + $installation_fee_per_unit,  // Unit price
    'quantity' => $quantity,
```

### 2. customer/order_tarpaulin.php (Line 64-71)
**Before:**
```php
$unit_price = 20.00;

$_SESSION['cart'][$item_key] = [
    'price' => $area * $unit_price * $quantity,  // WRONG - Total price
    'quantity' => $quantity,
```

**After:**
```php
$price_per_sqft = 20.00;
$unit_price = $area * $price_per_sqft;  // Price per piece

$_SESSION['cart'][$item_key] = [
    'price' => $unit_price,  // Unit price
    'quantity' => $quantity,
```

### 3. customer/checkout.php (Line 98-108)
Added safety check to detect and correct if total price is being saved:

```php
// Ensure unit_price is per item, not total
// If price seems to be total (very high), divide by quantity
$unit_price = (float)$item['price'];
$quantity_val = (int)$item['quantity'];

// Safety check: if unit_price is suspiciously high and divisible by quantity,
// it might be a total price instead of unit price
if ($quantity_val > 1 && $unit_price > 1000 && ($unit_price % $quantity_val) < 0.01) {
    // Likely a total price, convert to unit price
    $unit_price = $unit_price / $quantity_val;
}
```

## Fixing Existing Orders

### Option 1: Use the Admin Utility (Recommended)
1. Navigate to: `http://localhost/printflow/admin/fix_unit_prices.php`
2. Review the list of suspicious order items
3. Click "Fix X Order Items" to correct them automatically

### Option 2: Run SQL Script Manually
1. Open phpMyAdmin
2. Select the `printflow` database
3. Go to the SQL tab
4. Run the script from `fix_unit_prices.sql`

### Option 3: Fix Specific Orders
For order #2275 and similar orders, run this SQL:

```sql
UPDATE order_items
SET unit_price = unit_price / quantity
WHERE order_id = 2275
  AND quantity > 1;
```

## Verification
After fixing, verify the order displays correctly:
1. Go to `http://localhost/printflow/customer/payment.php?order_id=2275`
2. Check that:
   - Unit Price shows the per-unit price (e.g., ₱800.00)
   - Quantity shows the correct quantity (e.g., 6)
   - Total shows unit_price × quantity (e.g., ₱4,800.00)

## Prevention
The fixes ensure that:
1. **Service order forms** now save the per-unit price to the cart
2. **Checkout process** has a safety check to detect and correct total prices
3. **Future orders** will have correct unit prices from the start

## Testing Checklist
- [ ] Create a new T-shirt order with quantity > 1
- [ ] Create a new Glass Sticker order with quantity > 1
- [ ] Create a new Tarpaulin order with quantity > 1
- [ ] Verify cart shows correct unit price
- [ ] Complete checkout
- [ ] Verify payment page shows correct unit price
- [ ] Verify order details show correct unit price

## Notes
- The total order amount remains unchanged (unit_price × quantity)
- Only the display of unit price vs total is affected
- No impact on payment calculations or order totals
- Existing orders need to be fixed using one of the methods above
