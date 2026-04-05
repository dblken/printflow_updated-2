-- Dynamic Service Form Configuration System (Corrected)
-- This allows admins to control all form fields without code changes

-- Main service form configurations table
CREATE TABLE IF NOT EXISTS `service_form_configs` (
  `config_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `is_active` TINYINT(1) DEFAULT 0,
  `allow_custom_design` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Form fields table (stores all dynamic fields)
CREATE TABLE IF NOT EXISTS `service_form_fields` (
  `field_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `config_id` INT UNSIGNED NOT NULL,
  `step_number` TINYINT UNSIGNED NOT NULL,
  `field_name` VARCHAR(100) NOT NULL,
  `field_label` VARCHAR(255) NOT NULL,
  `field_type` ENUM('text','number','email','tel','textarea','select','radio','checkbox','file','date') NOT NULL,
  `options_json` JSON DEFAULT NULL COMMENT 'For select/radio/checkbox: ["Option 1","Option 2"]',
  `placeholder` VARCHAR(255) DEFAULT NULL,
  `default_value` VARCHAR(255) DEFAULT NULL,
  `is_required` TINYINT(1) DEFAULT 0,
  `validation_rules` JSON DEFAULT NULL COMMENT '{"min":1,"max":100,"pattern":"regex"}',
  `help_text` TEXT DEFAULT NULL,
  `display_order` INT UNSIGNED DEFAULT 0,
  `conditional_logic` JSON DEFAULT NULL COMMENT '{"show_if":{"field":"shirt_source","value":"Shop"}}',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`config_id`) REFERENCES `service_form_configs`(`config_id`) ON DELETE CASCADE,
  KEY `idx_config_step` (`config_id`, `step_number`),
  KEY `idx_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step configurations (for step titles/descriptions)
CREATE TABLE IF NOT EXISTS `service_form_steps` (
  `step_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `config_id` INT UNSIGNED NOT NULL,
  `step_number` TINYINT UNSIGNED NOT NULL,
  `step_title` VARCHAR(255) NOT NULL,
  `step_description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`config_id`) REFERENCES `service_form_configs`(`config_id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_config_step` (`config_id`, `step_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pricing rules (dynamic pricing based on selections)
CREATE TABLE IF NOT EXISTS `service_pricing_rules` (
  `rule_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
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
  FOREIGN KEY (`config_id`) REFERENCES `service_form_configs`(`config_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
