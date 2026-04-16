<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/store.php';
require_once __DIR__ . '/../bootstrap.php';

portalSessionStart();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']); exit;
}

if (!isLoggedInAsCustomer()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$phone   = trim($input['phone']   ?? '');
$address = trim($input['address'] ?? '');

if (empty($phone) || empty($address)) {
    echo json_encode(['success' => false, 'error' => 'Phone and address are required.']); exit;
}

$orderId = $_SESSION['portal_order_id'] ?? '';
if (empty($orderId)) {
    echo json_encode(['success' => false, 'error' => 'No order found.']); exit;
}

$order = getOrderById($orderId);
if (!$order) {
    echo json_encode(['success' => false, 'error' => 'Order not found.']); exit;
}

$svc = getPortalSheetsService();
if (!$svc) {
    echo json_encode(['success' => false, 'error' => 'Service unavailable.']); exit;
}

$sheetId = $_ENV['PORTAL_SHEET_ID'];
$row = $order['row'];

try {
    // Update phone (column D) and address (column E)
    $body = new \Google_Service_Sheets_ValueRange(['values' => [[$phone, $address]]]);
    $svc->spreadsheets_values->update($sheetId, "orders!D{$row}:E{$row}", $body, ['valueInputOption' => 'USER_ENTERED']);
    echo json_encode(['success' => true]);
} catch (\Exception $e) {
    error_log("update_profile error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to save. Please try again.']);
}
