<?php
require 'includes/db.php';
$r = db_query("SELECT c.name as cat_name, i.name as item_name, i.id, i.category_id 
               FROM inv_items i 
               JOIN inv_categories c ON i.category_id = c.id 
               WHERE i.status = 'ACTIVE' 
               ORDER BY c.name, i.name");
$out = "";
foreach ($r as $row) {
    $out .= $row['cat_name'] . " | " . $row['item_name'] . " (" . $row['id'] . ")\n";
}
file_put_contents('c:/xampp/htdocs/printflow/items_by_cat.txt', $out);
