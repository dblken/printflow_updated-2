-- Migration: Add Dynamic Pricing Support to Service Field Configuration
-- This enables per-option pricing for radio, select, and checkbox fields

-- Step 1: Add base_price to services table
ALTER TABLE services 
ADD COLUMN IF NOT EXISTS base_price DECIMAL(10,2) DEFAULT 0 COMMENT 'Base price before options' AFTER price;

-- Step 2: Modify service_field_configs to support option pricing
-- The field_options column already stores JSON, we'll enhance it to include prices
-- Example structure: [{"value": "With Layout", "price": 50}, {"value": "Without Layout", "price": 0}]

-- No schema changes needed for service_field_configs as we'll use the existing field_options JSON column
-- We just need to update the structure to include price in each option

-- Step 3: Add index for performance
CREATE INDEX IF NOT EXISTS idx_services_base_price ON services(base_price);

-- Migration complete
-- Next: Update PHP code to handle option pricing in JSON structure
