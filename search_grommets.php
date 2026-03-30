<?php
require 'includes/db.php';
$r = db_query("SELECT id, name FROM inv_items WHERE name LIKE '%GROMMET%'");
echo json_encode($r, JSON_PRETTY_PRINT);
