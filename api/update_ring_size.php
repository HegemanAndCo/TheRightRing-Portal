<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/store.php';

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');
requireCustomerLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
}

$input    = json_decode(file_get_contents('php://input'), true);
$ringSize = trim($input['ring_size'] ?? '');

if (empty($ringSize)) {
    jsonResponse(['success' => false, 'error' => 'Ring size is required.']);
}

$orderId = getCurrentOrderId();
$order   = getOrderById($orderId);

if (!$order) {
    jsonResponse(['success' => false, 'error' => 'Order not found.'], 404);
}

// Update ring_choices_json to replace/add the ring size entry
$choices = json_decode($order['ring_choices_json'], true) ?? [];

$found = false;
foreach ($choices as &$c) {
    if (($c['questionId'] ?? '') === 'ringSize') {
        $c['name'] = $ringSize;
        $found = true;
        break;
    }
}
unset($c);

if (!$found) {
    $choices[] = [
        'questionId'   => 'ringSize',
        'questionText' => 'Ring Size',
        'name'         => $ringSize,
        'imageUrl'     => '',
        'details'      => '',
    ];
}

$order['ring_choices_json'] = json_encode($choices);

$ok = updateOrder($order['row'], $order);

if (!$ok) {
    jsonResponse(['success' => false, 'error' => 'Could not save ring size.']);
}

// Notify admin by email
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
    $mail->Subject = "Ring Size Updated — {$order['customer_name']} ({$order['order_id']})";
    $mail->isHTML(false);
    $mail->Body = "Ring size updated for order {$order['order_id']}.\n\nCustomer: {$order['customer_name']} ({$order['email']})\nNew Ring Size: {$ringSize}\n\nView order: https://portal.therightring.com/admin-order.php?id={$order['order_id']}";
    $mail->send();
} catch (Exception $e) {
    error_log("Ring size admin email failed: " . $e->getMessage());
}

jsonResponse(['success' => true]);
