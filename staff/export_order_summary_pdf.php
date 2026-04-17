<?php
ob_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';
require_once __DIR__ . '/../vendor/autoload.php';

require_role(['Staff', 'Admin', 'Manager']);

// Initialize branch context
$b_context = init_branch_context();
$staffBranchId = $b_context['selected_branch_id'];
$branch_name = $b_context['branch_name'];

// Get filters
$status = $_GET['status'] ?? '';
$range = $_GET['range'] ?? 'week';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$where = [];
$params = [];
$types = '';

// Branch filtering
if ($staffBranchId !== 'all') {
    $where[] = "o.branch_id = ?";
    $params[] = $staffBranchId;
    $types .= 'i';
}

if ($status && $status !== 'ALL' && $status !== 'all') {
    $where[] = "o.status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($date_from) {
    $where[] = "DATE(o.order_date) >= ?";
    $params[] = $date_from;
    $types .= 's';
} elseif ($range) {
    if ($range === 'today') {
        $where[] = "DATE(o.order_date) = CURDATE()";
    } elseif ($range === 'week') {
        $where[] = "o.order_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)";
    } elseif ($range === 'month') {
        $where[] = "o.order_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)";
    } elseif ($range === 'year') {
        $where[] = "o.order_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)";
    }
}

if ($date_to) {
    $where[] = "DATE(o.order_date) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Fetch orders
$orders = db_query("
    SELECT 
        o.order_id,
        o.order_date,
        o.status,
        o.total_amount,
        o.order_type,
        CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,'')) as customer_name,
        (SELECT oi.customization_data FROM order_items oi WHERE oi.order_id = o.order_id LIMIT 1) as first_item_data
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.customer_id
    $where_sql
    ORDER BY o.order_date DESC
", $types, $params);

// Calculate summary
$total_orders = count($orders);
$total_sales = 0;
$pending = 0;
$ready = 0;
$completed = 0;

foreach ($orders as $order) {
    $total_sales += (float)$order['total_amount'];
    $s = $order['status'];
    if (in_array($s, ['Pending', 'Pending Review', 'For Revision'])) $pending++;
    if ($s === 'Ready for Pickup') $ready++;
    if (in_array($s, ['Completed', 'Rated'])) $completed++;
}

// Staff and formatting
$staff_printable_name = $_SESSION['user_name'] ?? 'Staff User';
$prepared_by = $staff_printable_name;
$nia_blue = [0, 74, 153]; // Formal blue

// Create PDF
class OrderSummaryPDF extends TCPDF {
    public $branch_label = 'Main Branch';
    public $prepared_by = '';
    public $nia_blue = [0, 74, 153];

    public function Header() {
        // Logo (Top-Left, Circular)
        $logo = __DIR__ . '/../public/assets/uploads/shop_logo_1774059623.jpg';
        if (file_exists($logo)) {
            // Circle Clip
            $this->StartTransform();
            $this->setAlpha(1);
            $this->Circle(26, 17, 10, 0, 360, 'CNZ');
            $this->Image($logo, 16, 7, 20, 20, '', '', '', false, 300, '', false, false, 0);
            $this->StopTransform();
        }
        
        // Centered Header Text
        $this->SetY(8);
        $this->SetFont('dejavusans', 'B', 12);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 6, 'Mr. and Mrs. Print Main', 0, 1, 'C');
        
        $this->SetFont('dejavusans', '', 8.5);
        $details = "#240 corner M.L. Quezon St., Cabuyao, Philippines, 4025\n"
                 . "Contact: 0921 212 2293 | Email: mrandmrsprints@gmail.com\n"
                 . "Facebook: Mr. and Mrs.Print Main";
        $this->MultiCell(0, 4, $details, 0, 'C');
        
        // Horizontal Line
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.6);
        $this->Line(15, 30, 195, 30);
    }
    
    public function Footer() {
        $this->SetY(-30);
        
        // Footer line
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.3);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        
        $this->SetY($this->GetY() + 4);
        $this->SetFont('dejavusans', '', 7.5);
        $this->SetTextColor(0, 0, 0);
        
        // Left Side: Address & Contacts
        $y = $this->GetY();
        $this->SetXY(15, $y);
        $address_text = "Mr. and Mrs. Print Main\n"
                      . "#240 corner M.L. Quezon St., Cabuyao, Philippines, 4025\n"
                      . "Website: mrandmrsprintsflow.com";
        $this->MultiCell(100, 3.5, $address_text, 0, 'L');
        
        // Audit Info
        $this->SetXY(15, $y + 11);
        $audit_text = "Prepared By: " . ($this->prepared_by ?: 'System') . " | Branch: " . ($this->branch_label ?: 'Main');
        $this->Cell(0, 4, $audit_text, 0, 0, 'L');
        
        // Right Side: Page & Timestamp
        $this->SetXY(115, $y + 11);
        $this->Cell(80, 4, 'Generated On: ' . date('M j, Y g:i A') . ' | Page ' . $this->getAliasNumPage(), 0, 1, 'R');
        $this->SetXY(115, $y + 15);
        $this->Cell(80, 4, 'PF-REP-SUMMARY-01-Rev00', 0, 0, 'R');
    }
}

$pdf = new OrderSummaryPDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->branch_label = $branch_name;
$pdf->prepared_by = $prepared_by;
$pdf->SetCreator('PrintFlow');
$pdf->SetAuthor($prepared_by);
$pdf->SetTitle('Order Summary Report');

// Margins
$pdf->SetMargins(15, 35, 15);
$pdf->SetHeaderMargin(0);
$pdf->SetFooterMargin(0);
$pdf->SetAutoPageBreak(true, 35);
$pdf->AddPage();

// ── META SECTION ──
$pdf->SetY(35);
$pdf->SetFont('dejavusans', 'B', 14);
$pdf->SetTextColor($nia_blue[0], $nia_blue[1], $nia_blue[2]);
$pdf->Cell(0, 8, 'Order Summary Details', 0, 1, 'L');

$pdf->SetFont('dejavusans', '', 9);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 5, 'Session Date: ' . date('F j, Y'), 0, 1, 'L');
$pdf->Cell(0, 5, 'Staff Account: ' . $prepared_by, 0, 1, 'L');
$pdf->Ln(5);

// ── MINIMAL SUMMARY ──
$pdf->SetFillColor(245, 245, 245);
$pdf->SetFont('dejavusans', 'B', 8.5);
$pdf->Cell(34, 8, ' TOTAL ORDERS', 1, 0, 'L', true);
$pdf->SetFont('dejavusans', '', 8.5);
$pdf->Cell(20, 8, $total_orders, 1, 0, 'C');

$pdf->SetFont('dejavusans', 'B', 8.5);
$pdf->Cell(45, 8, ' TOTAL GROSS SALES', 1, 0, 'L', true);
$pdf->SetFont('dejavusans', '', 8.5);
$pdf->Cell(81, 8, '₱ ' . number_format($total_sales, 2), 1, 1, 'R');
$pdf->Ln(5);


// ── MAIN TABLE ──
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetLineWidth(0.2);

// Header
$pdf->SetFont('dejavusans', 'B', 8.5);
$pdf->SetFillColor($nia_blue[0], $nia_blue[1], $nia_blue[2]);
$pdf->SetTextColor(255, 255, 255);
$h_h = 8;

$pdf->Cell(18, $h_h, 'Order #', 1, 0, 'C', true);
$pdf->Cell(45, $h_h, 'Customer Name', 1, 0, 'C', true);
$pdf->Cell(45, $h_h, 'Service Type', 1, 0, 'C', true);
$pdf->Cell(25, $h_h, 'Status', 1, 0, 'C', true);
$pdf->Cell(27, $h_h, 'Date Created', 1, 0, 'C', true);
$pdf->Cell(20, $h_h, 'Amount', 1, 1, 'C', true);

// Rows
$pdf->SetFont('dejavusans', '', 8);
$pdf->SetTextColor(0, 0, 0);

foreach ($orders as $order) {
    // Service type resolution
    $service_type = 'Product Order';
    if ($order['order_type'] === 'custom' && !empty($order['first_item_data'])) {
        $custom = json_decode($order['first_item_data'], true);
        $service_type = get_service_name_from_customization($custom, 'Custom Order');
    }
    
    $row_h = 7.5;
    $pdf->Cell(18, $row_h, $order['order_id'], 1, 0, 'C');
    $pdf->Cell(45, $row_h, substr($order['customer_name'], 0, 25), 1, 0, 'C');
    $pdf->Cell(45, $row_h, substr($service_type, 0, 30), 1, 0, 'C');
    $pdf->Cell(25, $row_h, $order['status'], 1, 0, 'C');
    $pdf->Cell(27, $row_h, date('Y-m-d', strtotime($order['order_date'])), 1, 0, 'C');
    $pdf->Cell(20, $row_h, number_format($order['total_amount'], 2), 1, 1, 'C');
}

// Clear internal buffers
ob_end_clean();

// Output
$filename = 'Order_Summary_' . date('Y-m-d_His') . '.pdf';
$pdf->Output($filename, 'I');
exit;




