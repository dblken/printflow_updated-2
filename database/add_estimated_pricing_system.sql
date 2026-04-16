-- Migration: Add Estimated Pricing System with Staff-Controlled Final Pricing
-- This enables inquiry-based ordering with staff approval workflow

-- Step 1: Add new statuses to orders table
ALTER TABLE orders 
MODIFY COLUMN status ENUM(
    'Pending',
    'Approved',
    'To Pay',
    'Processing',
    'Ready for Pickup',
    'Completed',
    'Cancelled'
) DEFAULT 'Pending';

-- Step 2: Add estimated_price and final_price columns
ALTER TABLE orders 
ADD COLUMN estimated_price DECIMAL(10,2) DEFAULT NULL COMMENT 'Customer-calculated estimated price' AFTER total_amount,
ADD COLUMN final_price DECIMAL(10,2) DEFAULT NULL COMMENT 'Staff-approved final price' AFTER estimated_price;

-- Step 3: Add indexes for performance
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_estimated_price ON orders(estimated_price);
CREATE INDEX idx_orders_final_price ON orders(final_price);

-- Step 4: Update existing orders to have estimated_price = total_amount
UPDATE orders 
SET estimated_price = total_amount 
WHERE estimated_price IS NULL;

-- Migration complete
-- Flow: Customer clicks "Inquire Now" → Status = Pending
--       Staff sets final_price → Status = Approved  
--       Customer clicks "Place Order" → Status = To Pay
