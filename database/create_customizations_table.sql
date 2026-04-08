CREATE TABLE IF NOT EXISTS `customizations` (
  `customization_id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `order_item_id` INT DEFAULT NULL,
  `customer_id` INT NOT NULL,
  `service_type` VARCHAR(100) NOT NULL,
  `customization_details` TEXT,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Pending Review',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_order` (`order_id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_order_item` (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
