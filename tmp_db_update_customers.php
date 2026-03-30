<?php
require 'includes/functions.php';
// Add last_activity to customers table
db_execute("ALTER TABLE customers ADD COLUMN last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
echo "Added last_activity to customers\n";
?>
