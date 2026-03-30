<?php
require 'includes/db.php';
$r = db_query('DESCRIBE order_items');
$out = "";
foreach ($r as $f) {
    $out .= $f['Field'] . ' - ' . $f['Type'] . "\n";
}
file_put_contents('schema_order_items.txt', $out);
echo "Schema written to schema_order_items.txt\n";
