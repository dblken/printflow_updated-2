# Database Migration Instructions

## Add amount_paid Column to Orders Table

The `amount_paid` column is needed to track partial payments and payment verification.

### Option 1: Run via phpMyAdmin (Recommended)

1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Select the `printflow` database
3. Click on the "SQL" tab
4. Copy and paste the contents of `database/migrations/add_amount_paid_to_orders.sql`
5. Click "Go" to execute

### Option 2: Run via MySQL Command Line

```bash
mysql -u root -p printflow < database/migrations/add_amount_paid_to_orders.sql
```

### Option 3: Quick Manual Fix

If you prefer to add the column manually:

```sql
-- Add the column
ALTER TABLE orders ADD COLUMN amount_paid DECIMAL(10,2) DEFAULT 0.00 AFTER total_amount;

-- Update existing data
UPDATE orders 
SET amount_paid = CASE 
    WHEN payment_status = 'Paid' THEN total_amount
    WHEN payment_status = 'Partial' THEN COALESCE(downpayment_amount, 0)
    ELSE 0
END;

-- Add index
ALTER TABLE orders ADD INDEX idx_amount_paid (amount_paid);
```

### Verification

After running the migration, verify it worked:

```sql
-- Check if column exists
SHOW COLUMNS FROM orders LIKE 'amount_paid';

-- Check sample data
SELECT order_id, total_amount, amount_paid, payment_status FROM orders LIMIT 10;
```

You should see the `amount_paid` column with appropriate values based on payment status.

### What This Column Does

- Tracks how much the customer has actually paid
- Supports partial payments (downpayments)
- Used in payment verification workflow
- Helps calculate remaining balance

### After Migration

Once the migration is complete, the system will:
- Track payment amounts accurately
- Support partial payment workflows
- Show correct payment status in orders
- Enable proper payment verification
