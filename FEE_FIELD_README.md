# Fixed Fee Field Feature

## Overview
This feature allows you to add fixed fees to services that are NOT multiplied by quantity. For example, a "Layout Fee" of ₱50 will be added once per order, regardless of whether the customer orders 1 or 100 items.

## Installation Steps

### 1. Run Database Migration
Execute the SQL file to add the fee field type support:

```sql
-- Run this in phpMyAdmin or MySQL command line
SOURCE C:\xampp\htdocs\printflow\database\add_fee_field_type.sql;
```

Or manually run these commands:
```sql
ALTER TABLE service_field_configs 
MODIFY COLUMN field_type ENUM('select', 'radio', 'text', 'number', 'file', 'date', 'textarea', 'dimension', 'quantity', 'fee') NOT NULL;

ALTER TABLE service_field_configs 
ADD COLUMN fee_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Fixed fee amount (not multiplied by quantity)' AFTER field_options;
```

### 2. How to Add a Fee Field

1. Go to **Admin Panel** → **Services Management**
2. Click **Configure Fields** on any service
3. Click **+ Add Field** button
4. Fill in the form:
   - **Field Label**: e.g., "Layout Fee", "Processing Fee", "Setup Fee"
   - **Field Type**: Select **"Fixed Fee (Not Multiplied)"**
   - **Fee Amount**: Enter the amount (e.g., 50.00)
   - **Required Field**: Toggle as needed
5. Click **Add Field**
6. Click **Save Configuration**

### 3. How It Works

**Customer View:**
- The fee is displayed as a fixed amount with a note: "This fee is added once per order (not multiplied by quantity)"
- Example display:
  ```
  Layout Fee *
  Fixed Fee: ₱50.00
  This fee is added once per order (not multiplied by quantity)
  ```

**Price Calculation:**
- Regular prices: `(Base Price + Option Prices) × Quantity`
- Fixed fees: Added once, NOT multiplied
- **Total = (Base + Options) × Quantity + Fixed Fees**

**Example:**
- Base Price: ₱100
- Size Option: ₱50
- Quantity: 5
- Layout Fee: ₱50 (fixed)
- **Total: (₱100 + ₱50) × 5 + ₱50 = ₱800**

### 4. Use Cases

Perfect for:
- Layout/Design fees
- Setup fees
- Processing fees
- Delivery fees (if fixed)
- One-time charges
- Service fees

### 5. Features

✅ Fixed amount per order
✅ NOT multiplied by quantity
✅ Displays clearly to customers
✅ Can be required or optional
✅ Supports conditional display (parent field logic)
✅ Included in estimated price calculation
✅ Visible in order summary

## Technical Details

### Files Modified
1. `database/add_fee_field_type.sql` - Database schema update
2. `admin/service_field_config.php` - Admin UI for adding fee fields
3. `includes/service_field_config_helper.php` - Backend logic for fee fields
4. `includes/service_field_renderer.php` - Frontend rendering and calculation
5. `customer/order_service_dynamic.php` - Customer page price calculation

### Database Schema
```sql
field_type ENUM(..., 'fee')
fee_amount DECIMAL(10,2) DEFAULT 0.00
```

### JavaScript Calculation
```javascript
// Fixed fees are added separately
const finalTotal = (unitPrice * quantity) + fixedFees;
```

## Troubleshooting

**Issue: Fee field not showing in dropdown**
- Make sure you ran the database migration
- Refresh the admin page (Ctrl+F5)

**Issue: Fee is being multiplied by quantity**
- Check that field_type is 'fee' in database
- Verify the JavaScript calculation is updated
- Clear browser cache

**Issue: Price not updating**
- Check browser console for errors
- Ensure calculateEstimatedPrice() function is defined
- Verify the .fixed-fee-field class is present on the hidden input

## Support

For issues or questions, check:
1. Browser console for JavaScript errors
2. PHP error logs for backend issues
3. Database structure matches the migration
