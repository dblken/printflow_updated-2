-- Add pricing support to service field options
-- This allows admin to assign prices to each option in radio, select, and checkbox fields

-- 1. Add price column to service_field_options table
ALTER TABLE service_field_options 
ADD COLUMN price DECIMAL(10,2) DEFAULT 0 AFTER option_value;

-- 2. Add base_price column to services table (optional but recommended)
ALTER TABLE services 
ADD COLUMN base_price DECIMAL(10,2) DEFAULT 0 AFTER price;

-- 3. Add index for better performance
CREATE INDEX idx_service_field_options_price ON service_field_options(price);

-- Note: Run this migration to enable dynamic pricing per field option
