<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sheets.php';
require_once __DIR__ . '/../lib/drive.php';

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

portalSessionStart();
$isAdmin    = isLoggedInAsAdmin();
$isCustomer = isLoggedInAsCustomer();

if (!$isAdmin && !$isCustomer) {
    jsonResponse(['success' => false, 'error' => 'Not authenticated.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
}

$input   = json_decode(file_get_contents('php://input'), true) ?? [];
$mediaId = trim($input['media_id'] ?? '');
$orderId = trim($input['order_id'] ?? '');

if (empty($mediaId) || empty($orderId)) {
    jsonResponse(['success' => false, 'error' => 'media_id and order_id required.']);
}

if ($isCustomer && getCurrentOrderId() !== $orderId) {
    jsonResponse(['success' => false, 'error' => 'Access denied.'], 403);
}

$result = deleteMediaRecord($mediaId, $orderId);

if ($result === false) {
    jsonResponse(['success' => false, 'error' => 'Could not delete media record.']);
}

// Remove local file if stored locally
$thumbnailUrl = $result['thumbnail_url'] ?? '';
if (!empty($thumbnailUrl) && str_starts_with($thumbnailUrl, '/uploads/')) {
    $localFile = __DIR__ . '/../' . ltrim($thumbnailUrl, '/');
    if (file_exists($localFile)) @unlink($localFile);
}

jsonResponse(['success' => true]);
