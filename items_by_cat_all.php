<?php
require 'includes/db.php';
$r = db_query("SELECT c.name as cat_name, i.name as item_name, i.id, i.category_id, i.status 
               FROM inv_items i 
               JOIN inv_categories c ON i.category_id = c.id 
               ORDER BY c.name, i.name");
$out = "";
foreach ($r as $row) {
    if (empty($row['cat_name'])) continue;
    $out .= $row['cat_name'] . " | " . $row['item_name'] . " (" . $row['id'] . ") [" . $row['status'] . "]\n";
}
file_put_contents('c:/xampp/htdocs/printflow/items_by_cat_all.txt', $out);
