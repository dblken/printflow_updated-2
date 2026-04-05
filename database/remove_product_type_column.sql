-- Remove product_type column from products table
-- Run this in phpMyAdmin or MySQL command line

-- Check if column exists before dropping (safe approach)
ALTER TABLE `products` DROP COLUMN IF EXISTS `product_type`;

-- Alternative if your MySQL version doesn't support DROP COLUMN IF EXISTS:
-- ALTER TABLE `products` DROP COLUMN `product_type`;
