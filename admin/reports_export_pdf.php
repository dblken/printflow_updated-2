<?php
/**
 * Professional PDF Export for Order Summary Report
 * PrintFlow - Admin Reports
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';
require_once __DIR__ . '/../vendor/autoload.php';

require_role(['Admin', 'Manager', 'Staff']);

use TCPDF;

$report = $_GET['report'] ?? 'orders';
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$branchCtx = init_branch_context(false);
$branchId = $branchCtx['selected_branch_id'];
$branchName = $branchCtx['branch_name'];

$from = date('Y-m-d', strtotime($from));
$to = date('Y-m-d', strtotime($to));
$toEnd = $to . ' 23:59:59';

[$bSql, $bTypes, $bParams] = branch_where_parts('o', $branchId);

// Get logged-in user info
$current_user = get_logged_in_user();
$prepared_by = trim(($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? ''));
$user_role = $current_user['role'] ?? 'Staff';

// Create PDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('PrintFlow System');
$pdf->SetAuthor($prepared_by);
$pdf->SetTitle('Order Summary Report');
$pdf->SetSubject('Order Summary Report');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 10);

// Sky Blue Header Background
$pdf->SetFillColor(135, 206, 235); // #87CEEB
$pdf->Rect(0, 0, 210, 45, 'F');

// Logo placeholder (if exists)
$logoPath = __DIR__ . '/../public/images/logo.jpg';
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 15, 8, 30, 0, '', '', '', false, 300, '', false, false, 0);
}

// Company Details (White text on sky blue)
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetXY(50, 10);
$pdf->Cell(0, 6, 'Mr. and Mrs. Print Main', 0, 1, 'L');

$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY(50, 18);
$pdf->MultiCell(0, 4, 
    "#240 corner M.L. Quezon St., Cabuyao, Philippines, 4025\n" .
    "Contact: 0921 212 2293\n" .
    "Email: mrandmrsprints@gmail.com\n" .
    "Facebook: Mr. and Mrs.Print Main", 
    0, 'L');

// Reset text color to black
$pdf->SetTextColor(0, 0, 0);

// Report Title
$pdf->SetY(50);
$pdf->SetFont('helvetica', 'B', 18);
$pdf->Cell(0, 8, 'ORDER SUMMARY REPORT', 0, 1, 'C');

// Date Generated
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'Date Generated: ' . date('F j, Y g:i A'), 0, 1, 'C');
$pdf->Cell(0, 6, 'Period: ' . date('M j, Y', strtotime($from)) . ' - ' . date('M j, Y', strtotime($to)), 0, 1, 'C');
$pdf->Ln(5);

// Summary Section
$summary = db_query(
    "SELECT COUNT(*) as total_orders,
            SUM(CASE WHEN o.payment_status='Paid' THEN o.total_amount ELSE 0 END) as total_sales,
            SUM(CASE WHEN o.status='Pending' OR o.status='Pending Review' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN o.status='Ready for Pickup' THEN 1 ELSE 0 END) as ready,
            SUM(CASE WHEN o.status='Completed' THEN 1 ELSE 0 END) as completed
     FROM orders o WHERE o.order_date BETWEEN ? AND ?$bSql",
    'ss'.$bTypes, array_merge([$from, $toEnd], $bParams)
);

$s = $summary[0] ?? [];
$totalOrders = (int)($s['total_orders'] ?? 0);
$totalSales = (float)($s['total_sales'] ?? 0);
$pending = (int)($s['pending'] ?? 0);
$ready = (int)($s['ready'] ?? 0);
$completed = (int)($s['completed'] ?? 0);

// Summary Box
$pdf->SetFillColor(245, 245, 245);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 8, 'SUMMARY', 1, 1, 'L', true);

$pdf->SetFont('helvetica', '', 10);
$summaryData = [
    ['Total Orders', number_format($totalOrders)],
    ['Total Sales', '₱ ' . number_format($totalSales, 2)],
    ['Pending Orders', number_format($pending)],
    ['Ready for Pickup', number_format($ready)],
    ['Completed Orders', number_format($completed)]
];

foreach ($summaryData as $row) {
    $pdf->Cell(90, 6, $row[0], 1, 0, 'L');
    $pdf->Cell(90, 6, $row[1], 1, 1, 'R');
}

$pdf->Ln(5);

// Orders Table
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor(135, 206, 235);
$pdf->SetTextColor(255, 255, 255);

$pdf->Cell(20, 7, 'Order #', 1, 0, 'C', true);
$pdf->Cell(50, 7, 'Customer Name', 1, 0, 'C', true);
$pdf->Cell(40, 7, 'Service Type', 1, 0, 'C', true);
$pdf->Cell(35, 7, 'Date & Time', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'Amount', 1, 0, 'C', true);
$pdf->Cell(20, 7, 'Status', 1, 1, 'C', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 9);

// Get orders
$orders = db_query(
    "SELECT o.order_id, 
            CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,'')) as customer_name,
            o.order_date, o.total_amount, o.status,
            (SELECT p.name FROM order_items oi 
             LEFT JOIN products p ON oi.product_id = p.product_id 
             WHERE oi.order_id = o.order_id LIMIT 1) as service_type
     FROM orders o
     LEFT JOIN customers c ON o.customer_id = c.customer_id
     WHERE o.order_date BETWEEN ? AND ?$bSql
     ORDER BY o.order_date DESC
     LIMIT 50",
    'ss'.$bTypes, array_merge([$from, $toEnd], $bParams)
);

$fill = false;
foreach ($orders as $order) {
    $pdf->SetFillColor(250, 250, 250);
    
    $pdf->Cell(20, 6, '#' . $order['order_id'], 1, 0, 'C', $fill);
    $pdf->Cell(50, 6, substr($order['customer_name'], 0, 25), 1, 0, 'L', $fill);
    $pdf->Cell(40, 6, substr($order['service_type'] ?? 'N/A', 0, 20), 1, 0, 'L', $fill);
    $pdf->Cell(35, 6, date('M j, Y H:i', strtotime($order['order_date'])), 1, 0, 'C', $fill);
    $pdf->Cell(25, 6, '₱' . number_format($order['total_amount'], 2), 1, 0, 'R', $fill);
    $pdf->Cell(20, 6, substr($order['status'], 0, 10), 1, 1, 'C', $fill);
    
    $fill = !$fill;
}

// Footer Section
$pdf->SetY(-25);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(60, 5, 'Prepared By: ' . $prepared_by, 0, 0, 'L');
$pdf->Cell(60, 5, 'Branch: ' . $branchName, 0, 0, 'C');
$pdf->Cell(60, 5, 'Page ' . $pdf->getAliasNumPage() . ' of ' . $pdf->getAliasNbPages(), 0, 1, 'R');
$pdf->Cell(0, 5, 'Generated: ' . date('F j, Y g:i A'), 0, 1, 'C');

// Output PDF
$filename = 'OrderSummary_' . date('Ymd_His') . '.pdf';
$pdf->Output($filename, 'D');
exit;
