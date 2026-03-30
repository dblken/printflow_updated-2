<?php
require_once 'c:\xampp\htdocs\printflow\includes\db.php';
global $conn;
$res = $conn->query("SHOW COLUMNS FROM orders");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
