<?php
/**
 * API: OCR Preview for Payment Receipt
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }

    if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        exit;
    }

    // Temporary upload for OCR
    $tmp_dir = __DIR__ . '/../tmp/ocr_tmp';
    if (!is_dir($tmp_dir)) {
        mkdir($tmp_dir, 0755, true);
    }

    $ext = strtolower(pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION));
    $tmp_name = uniqid('preview_') . '.' . $ext;
    $tmp_path = $tmp_dir . '/' . $tmp_name;

    if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $tmp_path)) {
        $ocr_details = extract_payment_details($tmp_path);
        
        // Cleanup temp file
        @unlink($tmp_path);

        echo json_encode([
            'success' => true,
            'details' => $ocr_details
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to process image']);
    }

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
