<?php
require 'includes/db.php';
$r = db_query('SELECT * FROM inv_categories ORDER BY sort_order ASC');
foreach ($r as $cat) {
    echo $cat['id'] . ' - ' . $cat['name'] . "\n";
}
