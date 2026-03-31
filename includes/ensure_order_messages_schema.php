<?php
/**
 * Ensures `order_messages.sender` allows 'System' (automated order-thread messages).
 * Older dumps used ENUM('Customer','Staff') only.
 */
function printflow_ensure_order_messages_schema(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    global $conn;
    $check = @$conn->query("SHOW COLUMNS FROM `order_messages` LIKE 'sender'");
    if (!$check || $check->num_rows === 0) {
        if ($check) {
            $check->free();
        }
        return;
    }
    $row = $check->fetch_assoc();
    $check->free();
    $type = $row['Type'] ?? '';
    if (stripos($type, 'System') === false) {
        $sql = "ALTER TABLE `order_messages` MODIFY COLUMN `sender` ENUM('Customer','Staff','System') NOT NULL DEFAULT 'Customer'";
        if (!@$conn->query($sql)) {
            error_log('ensure_order_messages_schema: ' . $conn->error);
        }
    }
    
    // Check and add reply_id to order_messages
    $check_reply = @$conn->query("SHOW COLUMNS FROM `order_messages` LIKE 'reply_id'");
    if ($check_reply && $check_reply->num_rows === 0) {
        $sql = "ALTER TABLE `order_messages` ADD COLUMN `reply_id` int DEFAULT NULL AFTER `order_id`";
        if (!@$conn->query($sql)) {
            error_log('ensure_order_messages_schema add reply_id: ' . $conn->error);
        }
    }
    if ($check_reply) $check_reply->free();

    $check_file_type = @$conn->query("SHOW COLUMNS FROM `order_messages` LIKE 'file_type'");
    if ($check_file_type && $check_file_type->num_rows === 0) {
        $sql = "ALTER TABLE `order_messages` 
            ADD COLUMN `file_type` ENUM('text','image','video') DEFAULT 'text' AFTER `message`,
            ADD COLUMN `file_path` VARCHAR(255) NULL AFTER `file_type`,
            ADD COLUMN `file_size` INT NULL AFTER `file_path`,
            ADD COLUMN `file_name` VARCHAR(255) NULL AFTER `file_size`";
        @$conn->query($sql);
    }
    if ($check_file_type) $check_file_type->free();

    // Create message_reactions table if not exists
    $sql_reactions = "CREATE TABLE IF NOT EXISTS `message_reactions` (
        `reaction_id` int NOT NULL AUTO_INCREMENT,
        `message_id` int NOT NULL,
        `sender` enum('Customer','Staff','System') NOT NULL,
        `sender_id` int NOT NULL,
        `reaction_type` varchar(20) NOT NULL,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`reaction_id`),
        UNIQUE KEY `unique_reaction` (`message_id`, `sender`, `sender_id`),
        CONSTRAINT `fk_msg_reaction` FOREIGN KEY (`message_id`) REFERENCES `order_messages` (`message_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;";
    
    if (!@$conn->query($sql_reactions)) {
        error_log('ensure_order_messages_schema create message_reactions: ' . $conn->error);
    }
}
