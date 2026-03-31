# PowerShell Script to Fix All API Files
# Adds api_header.php to all API endpoints to prevent HTML in JSON responses

$apiFiles = @(
    "c:\xampp\htdocs\printflow\admin\api_address.php",
    "c:\xampp\htdocs\printflow\admin\api_branch.php",
    "c:\xampp\htdocs\printflow\admin\api_chatbot_conversations.php",
    "c:\xampp\htdocs\printflow\admin\api_customer_activity.php",
    "c:\xampp\htdocs\printflow\admin\api_generate_product_sku.php",
    "c:\xampp\htdocs\printflow\admin\api_order_status_chart.php",
    "c:\xampp\htdocs\printflow\admin\api_reports_heatmap.php",
    "c:\xampp\htdocs\printflow\admin\api_revenue_chart.php",
    "c:\xampp\htdocs\printflow\admin\api_update_user_status.php",
    "c:\xampp\htdocs\printflow\admin\api_verify_job_payment.php",
    "c:\xampp\htdocs\printflow\staff\api\get_products.php",
    "c:\xampp\htdocs\printflow\staff\api\notification_count.php",
    "c:\xampp\htdocs\printflow\staff\api\pos_add_customer.php",
    "c:\xampp\htdocs\printflow\staff\api\pos_cart_handler.php",
    "c:\xampp\htdocs\printflow\staff\api\pos_checkout.php",
    "c:\xampp\htdocs\printflow\staff\api\reports_export.php",
    "c:\xampp\htdocs\printflow\staff\api\service_order_api.php",
    "c:\xampp\htdocs\printflow\customer\api_add_to_cart_reflectorized.php",
    "c:\xampp\htdocs\printflow\customer\api_add_to_cart_souvenirs.php",
    "c:\xampp\htdocs\printflow\customer\api_address.php",
    "c:\xampp\htdocs\printflow\customer\api_cart.php",
    "c:\xampp\htdocs\printflow\customer\api_customer_orders.php",
    "c:\xampp\htdocs\printflow\customer\api_profile.php",
    "c:\xampp\htdocs\printflow\customer\api_reflectorized_order.php",
    "c:\xampp\htdocs\printflow\customer\api_submit_payment.php",
    "c:\xampp\htdocs\printflow\customer\api_track.php"
)

$fixed = 0
$skipped = 0
$errors = 0

foreach ($file in $apiFiles) {
    if (-not (Test-Path $file)) {
        Write-Host "SKIP: File not found - $file" -ForegroundColor Yellow
        $skipped++
        continue
    }
    
    try {
        $content = Get-Content $file -Raw -Encoding UTF8
        
        # Check if already has api_header.php
        if ($content -match "api_header\.php") {
            Write-Host "SKIP: Already fixed - $file" -ForegroundColor Cyan
            $skipped++
            continue
        }
        
        # Check if has header('Content-Type: application/json')
        if ($content -match "header\s*\(\s*['""]Content-Type:\s*application/json") {
            # Create backup
            $backup = $file + ".backup"
            Copy-Item $file $backup -Force
            
            # Replace pattern
            $newContent = $content -replace "(<\?php[^>]*?>[\r\n\s]*(?:/\*\*.*?\*/[\r\n\s]*)?)((?:require_once.*?[\r\n]+)*)", "`$1require_once __DIR__ . '/../includes/api_header.php';`r`n`$2"
            $newContent = $newContent -replace "header\s*\(\s*['""]Content-Type:\s*application/json['""].*?\)\s*;[\r\n]*", ""
            
            Set-Content $file $newContent -Encoding UTF8 -NoNewline
            
            Write-Host "FIXED: $file" -ForegroundColor Green
            $fixed++
        } else {
            Write-Host "SKIP: No JSON header found - $file" -ForegroundColor Yellow
            $skipped++
        }
    } catch {
        Write-Host "ERROR: Failed to process $file - $_" -ForegroundColor Red
        $errors++
    }
}

Write-Host "`n========================================" -ForegroundColor White
Write-Host "SUMMARY:" -ForegroundColor White
Write-Host "  Fixed: $fixed" -ForegroundColor Green
Write-Host "  Skipped: $skipped" -ForegroundColor Yellow
Write-Host "  Errors: $errors" -ForegroundColor Red
Write-Host "========================================" -ForegroundColor White

if ($fixed -gt 0) {
    Write-Host "`nBackup files created with .backup extension" -ForegroundColor Cyan
    Write-Host "Test the APIs and delete backups if everything works" -ForegroundColor Cyan
}
