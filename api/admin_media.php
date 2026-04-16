<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sheets.php';

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');
requireAdminLogin();

$orderId = trim($_GET['order_id'] ?? '');
if (empty($orderId)) {
    jsonResponse(['success' => false, 'error' => 'order_id required.'], 400);
}

$media = getMediaForOrder($orderId);
jsonResponse(['success' => true, 'media' => $media]);
