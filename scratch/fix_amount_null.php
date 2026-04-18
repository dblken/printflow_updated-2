<?php
require_once __DIR__ . '/../includes/db.php';
$res = mysqli_query($conn, "ALTER TABLE payments MODIFY COLUMN amount DECIMAL(10,2) NULL");
if ($res) {
    echo "Successfully made amount nullable.";
} else {
    echo "Error: " . mysqli_error($conn);
}
