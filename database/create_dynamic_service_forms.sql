-- Dynamic Service Form Configuration System
-- This allows admins to control all form fields without code changes

-- Main service form configurations table
CREATE TABLE IF NOT EXISTS `service_form_configs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `service_id` INT UNSIGNED NOT NULL,
  `form_title` VARCHAR(255) DEFAULT 'Product Customization',
  `total_steps` TINYINT UNSIGNED DEFAULT 4,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`service_id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_service` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Form fields table (stores all dynamic fields)
CREATE TABLE IF NOT EXISTS `service_form_fields` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `config_id` INT UNSIGNED NOT NULL,
  `step_number` TINYINT UNSIGNED NOT NULL,
  `field_name` VARCHAR(100) NOT NULL,
  `field_label` VARCHAR(255) NOT NULL,
  `field_type` ENUM('text','number','email','tel','textarea','select','radio','checkbox','file','date','toggle','hidden') NOT NULL,
  `field_options` JSON DEFAULT NULL COMMENT 'For select/radio/checkbox: ["Option 1","Option 2"]',
  `placeholder` VARCHAR(255) DEFAULT NULL,
  `default_value` VARCHAR(255) DEFAULT NULL,
  `is_required` TINYINT(1) DEFAULT 0,
  `is_visible` TINYINT(1) DEFAULT 1,
  `validation_rules` JSON DEFAULT NULL COMMENT '{"min":1,"max":100,"pattern":"regex"}',
  `help_text` TEXT DEFAULT NULL,
  `display_order` INT UNSIGNED DEFAULT 0,
  `conditional_logic` JSON DEFAULT NULL COMMENT '{"show_if":{"field":"shirt_source","value":"Shop"}}',
  `css_classes` VARCHAR(255) DEFAULT NULL,
  `grid_column` VARCHAR(50) DEFAULT '1fr' COMMENT 'CSS grid column span',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`config_id`) REFERENCES `service_form_configs`(`id`) ON DELETE CASCADE,
  KEY `idx_config_step` (`config_id`, `step_number`),
  KEY `idx_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step configurations (optional - for step titles/descriptions)
CREATE TABLE IF NOT EXISTS `service_form_steps` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `config_id` INT UNSIGNED NOT NULL,
  `step_number` TINYINT UNSIGNED NOT NULL,
  `step_title` VARCHAR(255) NOT NULL,
  `step_description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`config_id`) REFERENCES `service_form_configs`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_config_step` (`config_id`, `step_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pricing rules (dynamic pricing based on selections)
CREATE TABLE IF NOT EXISTS `service_pricing_rules` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `config_id` INT UNSIGNED NOT NULL,
  `rule_name` VARCHAR(255) NOT NULL,
  `rule_type` ENUM('base','multiplier','addon','conditional') NOT NULL,
  `trigger_field` VARCHAR(100) DEFAULT NULL,
  `trigger_value` VARCHAR(255) DEFAULT NULL,
  `price_adjustment` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `calculation_formula` TEXT DEFAULT NULL COMMENT 'For complex calculations',
  `is_active` TINYINT(1) DEFAULT 1,
  `display_order` INT UNSIGNED DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`config_id`) REFERENCES `service_form_configs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Store customer responses in flexible JSON format
ALTER TABLE `job_orders` 
ADD COLUMN IF NOT EXISTS `form_data` JSON DEFAULT NULL COMMENT 'Dynamic form responses';

-- Migration: Copy existing hardcoded data to new system
-- This will be done via PHP migration script to preserve existing orders
