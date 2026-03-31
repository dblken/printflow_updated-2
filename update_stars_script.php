<?php
$files = glob("c:/xampp/htdocs/printflow/customer/order_*.php");
$success = 0;

foreach ($files as $file) {
    if (strpos($file, 'order_tarpaulin') !== false) {
        continue;
    }
    
    $content = file_get_contents($file);
    $keyword = basename($file, '.php');
    
    // The exact regex pattern to find the old static rating block in all service pages.
    $pattern = '/<div class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-100">\s*<div class="flex items-center text-yellow-400">\s*<svg class="w-4 h-4 fill-current".*?<\/svg>\s*<span class="text-sm font-bold text-gray-900 ml-1">.*?<\/span>\s*<\/div>\s*<div class="h-4 w-px bg-gray-200"><\/div>\s*<div class="text-sm text-gray-500">.*?Reviews<\/div>\s*<div class="h-4 w-px bg-gray-200"><\/div>\s*<div class="text-sm text-gray-500">.*?Sold<\/div>\s*<\/div>/s';
    
    $php_snippet = "
                <?php
                \$stats = service_order_get_page_stats('$keyword');
                \$raw_avg = (float)(\$stats['avg_rating'] ?? 0);
                \$review_count = (int)(\$stats['review_count'] ?? 0);
                \$sold_count = (int)(\$stats['sold_count'] ?? 0);
                \$sold_display = \$sold_count >= 1000 ? number_format(\$sold_count / 1000, 1) . 'k' : \$sold_count;
                
                \$_s_name = 'PrintFlow Service';
                \$_s_row = db_query(\"SELECT name FROM services WHERE customer_link LIKE '%$keyword%' LIMIT 1\");
                if(!empty(\$_s_row)) { \$_s_name = \$_s_row[0]['name']; }
                ?>
                <div class=\"flex items-center gap-4 mb-6 pb-6 border-b border-gray-100\">
                    <div class=\"flex items-center gap-1\">
                        <?php for(\$i=1; \$i<=5; \$i++): ?>
                            <svg class=\"w-4 h-4\" style=\"fill: <?php echo (\$i <= round(\$raw_avg)) ? '#FBBF24' : '#E2E8F0'; ?>;\" viewBox=\"0 0 20 20\"><path d=\"M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z\"/></svg>
                        <?php endfor; ?>
                        
                        <?php if (\$review_count > 0): ?>
                            <a href=\"reviews.php?service=<?php echo urlencode(\$_s_name); ?>\" class=\"text-sm text-gray-500 hover:text-blue-500 hover:underline ml-1 cursor-pointer\">(<?php echo number_format(\$review_count); ?> Reviews)</a>
                        <?php endif; ?>
                    </div>
                    <div class=\"h-4 w-px bg-gray-200\"></div>
                    <div class=\"text-sm text-gray-500\"><?php echo \$sold_display; ?> Sold</div>
                </div>";
                
    $new_content = preg_replace($pattern, ltrim($php_snippet), $content);
    
    if ($new_content !== null && $content !== $new_content) {
        file_put_contents($file, $new_content);
        $success++;
        echo "Updated: $keyword\n";
    } else {
        echo "Skip or no match: $keyword\n";
    }
}
echo "\nTotal replaced: $success\n";
?>
