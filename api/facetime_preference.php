<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/store.php';

header('Content-Type: application/json');
requireCustomerLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
}

$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$requested = !empty($input['facetime_requested']) ? '1' : '0';

$orderId = getCurrentOrderId();
$order   = getOrderById($orderId);

if (!$order) {
    jsonResponse(['success' => false, 'error' => 'Order not found.'], 404);
}

$order['facetime_requested'] = $requested;
$ok = updateOrder($order['row'], $order);

if (!$ok) {
    jsonResponse(['success' => false, 'error' => 'Could not save preference. Please try again.']);
}

jsonResponse(['success' => true]);
