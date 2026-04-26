<?php
$htdocs_root = realpath(__DIR__ . '/../');
$design_file = '/printflow/uploads/orders/design_69ecb90f8d90a_1777121551.jpg';
$full_path = $htdocs_root . $design_file;
echo "htdocs_root: " . $htdocs_root . "\n";
echo "full_path: " . $full_path . "\n";
echo "file_exists: " . (file_exists($full_path) ? 'Yes' : 'No') . "\n";
