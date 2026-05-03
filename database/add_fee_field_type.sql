-- Add 'fee' field type to service_field_configs table
-- This allows admin to set fixed fees that don't multiply with quantity

ALTER TABLE service_field_configs 
MODIFY COLUMN field_type ENUM('select', 'radio', 'text', 'number', 'file', 'date', 'textarea', 'dimension', 'quantity', 'fee') NOT NULL;

-- Add fee_amount column to store the fixed fee value
ALTER TABLE service_field_configs 
ADD COLUMN fee_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Fixed fee amount (not multiplied by quantity)' AFTER field_options;
