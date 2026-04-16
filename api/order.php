<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/store.php';

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');
requireCustomerLogin();

$orderId = getCurrentOrderId();
$order   = getOrderById($orderId);

if (!$order) {
    jsonResponse(['success' => false, 'error' => 'Order not found.'], 404);
}

$media = getMediaForOrder($orderId);

// Decode ring choices JSON
$choices = json_decode($order['ring_choices_json'], true) ?? [];

jsonResponse([
    'success' => true,
    'order'   => $order,
    'choices' => $choices,
    'media'   => $media,
]);
