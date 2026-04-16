<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/store.php';

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');
requireCustomerLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
}

$input     = json_decode(file_get_contents('php://input'), true);
$versionId = trim($input['version_id'] ?? '');

if (empty($versionId)) {
    jsonResponse(['success' => false, 'error' => 'Version ID is required.']);
}

$orderId = getCurrentOrderId();
$order   = getOrderById($orderId);

if (!$order) {
    jsonResponse(['success' => false, 'error' => 'Order not found.'], 404);
}

$versions = json_decode($order['versions_json'], true) ?? [];
$approved = null;
foreach ($versions as $v) {
    if (($v['id'] ?? '') === $versionId) {
        $approved = $v;
        break;
    }
}

if (!$approved) {
    jsonResponse(['success' => false, 'error' => 'Version not found.']);
}

$versionLabel = $approved['label'] ?? $versionId;

// Persist approval, advance status, and set admin notification flag
$order['approved_version_id']        = $versionId;
$order['status']                     = '3D Printing Resin/Wax Model';
$order['ring_approved_notification'] = $versionLabel;
updateOrder($order['row'], $order);

// Send email to admin
try {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['SMTP_USERNAME'];
    $mail->Password   = $_ENV['SMTP_PASSWORD'];
    $mail->SMTPSecure = 'tls';
    $mail->Port       = (int)$_ENV['SMTP_PORT'];
    $mail->setFrom($_ENV['SMTP_FROM_EMAIL'], $_ENV['SMTP_FROM_NAME']);
    $mail->addAddress('design@therightring.com');
    $mail->Subject = "Ring Approved — {$order['customer_name']} ({$order['order_id']})";
    $mail->isHTML(false);
    $mail->Body =
        "A customer has approved their ring design.\n\n" .
        "Customer: {$order['customer_name']} ({$order['email']})\n" .
        "Order ID: {$order['order_id']}\n" .
        "Approved Version: {$versionLabel}\n\n" .
        "View order: https://portal.therightring.com/admin-order.php?id={$order['order_id']}";
    $mail->send();
} catch (Exception $e) {
    error_log("Approve ring email failed: " . $e->getMessage());
}

jsonResponse(['success' => true, 'version_label' => $versionLabel]);
