<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/store.php';

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');
requireCustomerLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
}

$orderId = getCurrentOrderId();
$order   = getOrderById($orderId);

if (!$order) {
    jsonResponse(['success' => false, 'error' => 'Order not found.'], 404);
}

// Only allow when on the 3D printing step
if ($order['status'] !== '3D Printing Resin/Wax Model') {
    jsonResponse(['success' => false, 'error' => 'This option is only available during the 3D printing step.']);
}

// Already requested
if ($order['skip_resin_requested'] === '1') {
    jsonResponse(['success' => true, 'already' => true]);
}

// Save the flag and advance status to In Production
$order['skip_resin_requested'] = '1';
$order['status'] = 'In Production';
$ok = updateOrder($order['row'], $order);

if (!$ok) {
    jsonResponse(['success' => false, 'error' => 'Could not save request. Please try again.']);
}

// Email admin
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
    $mail->Subject = "Skip 3D Resin Requested - {$order['customer_name']} ({$order['order_id']})";
    $mail->isHTML(false);
    $mail->Body = "Customer {$order['customer_name']} ({$order['email']}) has requested to skip the 3D Printing Resin/Wax Model step for order {$order['order_id']}.\n\nView order: https://portal.therightring.com/admin-order.php?id={$order['order_id']}";
    $mail->send();
} catch (Exception $e) {
    error_log("Skip resin admin email failed: " . $e->getMessage());
}

jsonResponse(['success' => true]);
