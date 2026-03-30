<?php
require 'includes/db.php';
$r = db_query('DESCRIBE order_item_revisions');
$out = "";
foreach ($r as $f) {
    $out .= $f['Field'] . ' - ' . $f['Type'] . "\n";
}
file_put_contents('schema_revisions.txt', $out);
