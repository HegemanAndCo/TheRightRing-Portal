<?php
/**
 * Stripe webhook to mark payments received in Google Sheets.
 * Register this URL in your Stripe dashboard:
 *   https://portal.therightring.com/api/payment_webhook.php
 * Events to listen for: checkout.session.completed
 */

require_once __DIR__ . '/../bootstrap.php';

require_once __DIR__ . '/../lib/sheets.php';

function sendCarePlanAdminEmail(array $order, float $amount): void {
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
        $mail->Subject = "Care Plan Purchased — {$order['customer_name']} ({$order['order_id']})";
        $mail->isHTML(false);
        $mail->Body = "Customer {$order['customer_name']} ({$order['email']}) purchased the Jewelers Mutual Lifetime Care Plan for \${$amount} on order {$order['order_id']}.\n\nSubmit enrollment to Jewelers Mutual.\n\nView order: https://portal.therightring.com/admin-order.php?id={$order['order_id']}";
        $mail->send();
    } catch (Exception $e) {
        error_log("Care plan admin email failed: " . $e->getMessage());
    }
}

$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$secret    = $_ENV['STRIPE_PORTAL_WEBHOOK_SECRET'] ?? '';

http_response_code(200);
header('Content-Type: application/json');

if (!empty($secret)) {
    try {
        $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
} else {
    // Webhook secret not set — parse without verification (not recommended for production)
    $event = json_decode($payload);
}

if (($event->type ?? '') === 'checkout.session.completed') {
    $session  = $event->data->object;
    $orderId  = $session->metadata->order_id ?? '';
    $payType  = $session->metadata->payment_type ?? '';
    $amount   = ($session->amount_total ?? 0) / 100;

    if ($orderId && $payType) {
        markPaymentReceived($orderId, $amount, $payType);
        error_log("Portal payment recorded: order={$orderId} type={$payType} amount={$amount}");
        if ($payType === 'care-plan' || $payType === 'final-with-care-plan') {
            $order = getOrderById($orderId);
            if ($order) sendCarePlanAdminEmail($order, $amount);
        }
    }
}

echo json_encode(['received' => true]);
