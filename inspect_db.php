<?php
require_once __DIR__ . '/includes/db.php';
global $conn;

$res = $conn->query("DESCRIBE order_messages");
$out = "";
while($row = $res->fetch_assoc()) {
    $out .= $row['Field'] . " (" . $row['Type'] . ")\n";
}
file_put_contents('db_dump.txt', $out);
echo "Dumped schema to db_dump.txt";
