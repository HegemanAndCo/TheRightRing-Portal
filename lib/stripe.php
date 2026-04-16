<?php
/**
 * Stripe Checkout session creation for portal payments.
 */

// Autoload loaded via bootstrap.php

function createPortalPaymentSession(array $opts): array {
    // $opts: order_id, customer_name, customer_email, amount_cents, payment_type ('progress'|'final'), description
    $secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? '';
    if (empty($secretKey)) {
        return ['success' => false, 'error' => 'Stripe not configured'];
    }

    \Stripe\Stripe::setApiKey($secretKey);

    $siteUrl    = rtrim($_ENV['SITE_URL'] ?? 'https://portal.therightring.com', '/');
    $orderId    = urlencode($opts['order_id']);
    $payType    = urlencode($opts['payment_type']);

    try {
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'payment_method_options' => [
                'card' => ['request_three_d_secure' => 'automatic'],
            ],
            'line_items' => $opts['line_items'] ?? [[
                'price_data' => [
                    'currency'     => 'usd',
                    'unit_amount'  => (int)$opts['amount_cents'],
                    'product_data' => [
                        'name'        => $opts['description'],
                        'description' => 'The Right Ring — Custom Engagement Ring',
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode'         => 'payment',
            'customer_email' => $opts['customer_email'],
            'metadata' => [
                'order_id'     => $opts['order_id'],
                'customer_name'=> $opts['customer_name'],
                'payment_type' => $opts['payment_type'],
            ],
            'success_url' => "https://portal.therightring.com/portal.php?payment_success=1&order_id={$orderId}&type={$payType}",
            'cancel_url'  => "https://portal.therightring.com/portal.php?payment_cancelled=1",
            'payment_intent_data' => [
                'metadata' => [
                    'order_id'     => $opts['order_id'],
                    'payment_type' => $opts['payment_type'],
                ],
            ],
        ]);

        return ['success' => true, 'url' => $session->url];
    } catch (\Exception $e) {
        error_log("Portal Stripe error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
