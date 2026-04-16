<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/store.php';
require_once __DIR__ . '/../lib/stripe.php';

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');
requireCustomerLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
}

$input       = json_decode(file_get_contents('php://input'), true);
$paymentType = $input['payment_type'] ?? ''; // 'deposit' | 'progress' | 'final' | 'care-plan' | 'final-with-care-plan'

if (!in_array($paymentType, ['deposit', 'progress', 'final', 'care-plan', 'final-with-care-plan'])) {
    jsonResponse(['success' => false, 'error' => 'Invalid payment type.']);
}

$orderId = getCurrentOrderId();
$order   = getOrderById($orderId);

if (!$order) {
    jsonResponse(['success' => false, 'error' => 'Order not found.'], 404);
}

function getCarePlanPrice(float $total): ?float {
    $tiers = [
        [9.99,    99.99,    29.99],
        [100,     199.99,   49.99],
        [200,     349.99,   79.99],
        [350,     499.99,   99.99],
        [500,     749.99,   119.99],
        [750,     999.99,   169.99],
        [1000,    1499.99,  214.99],
        [1500,    1999.99,  249.99],
        [2000,    2499.99,  269.99],
        [2500,    2999.99,  299.99],
        [3000,    3999.99,  349.99],
        [4000,    4999.99,  399.99],
        [5000,    5999.99,  439.99],
        [6000,    7999.99,  499.99],
        [8000,    9999.99,  579.99],
        [10000,   14999.99, 799.99],
        [15000,   19999.99, 999.99],
        [20000,   29999.99, 1349.99],
    ];
    foreach ($tiers as [$from, $to, $price]) {
        if ($total >= $from && $total <= $to) return (float)$price;
    }
    return null;
}

// Determine amount
if ($paymentType === 'deposit') {
    if ((float)$order['deposit_paid'] > 0) {
        jsonResponse(['success' => false, 'error' => 'Deposit has already been paid.']);
    }
    $amountDollars = 500;
    $description   = 'Initial Design Deposit — The Right Ring';
} elseif ($paymentType === 'progress') {
    $amountDollars = (float)$order['progress_deposit_due'];
    $description   = 'Progress Deposit — The Right Ring';
} elseif ($paymentType === 'final') {
    if (!$order['final_payment_enabled']) {
        jsonResponse(['success' => false, 'error' => 'Final payment is not yet enabled for this order.'], 403);
    }
    $finalDue       = (float)$order['final_payment_due'];
    $shippingCharge = (float)$order['shipping_charge'];
    $amountDollars  = $finalDue + $shippingCharge;
    $description    = 'Final Payment — The Right Ring';
    $lineItems = [
        ['price_data' => ['currency' => 'usd', 'unit_amount' => (int)round($finalDue * 100), 'product_data' => ['name' => 'Final Payment — Custom Ring']], 'quantity' => 1],
    ];
    if ($shippingCharge > 0) {
        $lineItems[] = ['price_data' => ['currency' => 'usd', 'unit_amount' => (int)round($shippingCharge * 100), 'product_data' => ['name' => 'Shipping']], 'quantity' => 1];
    }
} elseif ($paymentType === 'care-plan') {
    // Standalone care plan (edge case — UI uses final-with-care-plan)
    if (($order['care_plan_purchased'] ?? '') === '1') {
        jsonResponse(['success' => false, 'error' => 'Care plan already purchased.']);
    }
    $ringTotal     = (float)$order['total_estimate'];
    $planBase      = getCarePlanPrice($ringTotal);
    if ($planBase === null) {
        jsonResponse(['success' => false, 'error' => 'Ring total is outside care plan pricing range.']);
    }
    $planTax       = round($planBase * 0.07, 2);
    $pretax        = $planBase + $planTax;
    $stripeFee     = round(($pretax * 0.029) + 0.30, 2);
    $amountDollars = $pretax + $stripeFee;
    $description   = 'Jewelers Mutual Lifetime Care Plan — The Right Ring';
    $lineItems = [
        ['price_data' => ['currency' => 'usd', 'unit_amount' => (int)round($planBase * 100),  'product_data' => ['name' => 'Lifetime Care Plan (base price)']], 'quantity' => 1],
        ['price_data' => ['currency' => 'usd', 'unit_amount' => (int)round($planTax * 100),   'product_data' => ['name' => 'Care Plan Sales Tax (7%)']], 'quantity' => 1],
        ['price_data' => ['currency' => 'usd', 'unit_amount' => (int)round($stripeFee * 100), 'product_data' => ['name' => 'Processing Fee (2.9% + $0.30)']], 'quantity' => 1],
    ];
} elseif ($paymentType === 'final-with-care-plan') {
    if (!$order['final_payment_enabled']) {
        jsonResponse(['success' => false, 'error' => 'Final payment is not yet enabled for this order.'], 403);
    }
    if (($order['care_plan_purchased'] ?? '') === '1') {
        jsonResponse(['success' => false, 'error' => 'Care plan already purchased.']);
    }
    $finalDue       = (float)$order['final_payment_due'];
    $shippingCharge = (float)$order['shipping_charge'];
    $ringTotal      = (float)$order['total_estimate'];
    $planBase       = getCarePlanPrice($ringTotal);
    if ($planBase === null) {
        jsonResponse(['success' => false, 'error' => 'Ring total is outside care plan pricing range.']);
    }
    $planTax          = round($planBase * 0.07, 2);
    $carePlanSubtotal = $planBase + $planTax;
    $stripeFee        = round(($carePlanSubtotal * 0.029) + 0.30, 2);
    $amountDollars    = $finalDue + $carePlanSubtotal + $stripeFee + $shippingCharge;
    $description      = 'Final Payment + Lifetime Care Plan — The Right Ring';
    $lineItems = [
        ['price_data' => ['currency' => 'usd', 'unit_amount' => (int)round($finalDue * 100),  'product_data' => ['name' => 'Final Payment — Custom Ring']], 'quantity' => 1],
        ['price_data' => ['currency' => 'usd', 'unit_amount' => (int)round($planBase * 100),  'product_data' => ['name' => 'Lifetime Care Plan (base price)']], 'quantity' => 1],
        ['price_data' => ['currency' => 'usd', 'unit_amount' => (int)round($planTax * 100),   'product_data' => ['name' => 'Care Plan Sales Tax (7%)']], 'quantity' => 1],
        ['price_data' => ['currency' => 'usd', 'unit_amount' => (int)round($stripeFee * 100), 'product_data' => ['name' => 'Processing Fee (2.9% + $0.30)']], 'quantity' => 1],
    ];
    if ($shippingCharge > 0) {
        $lineItems[] = ['price_data' => ['currency' => 'usd', 'unit_amount' => (int)round($shippingCharge * 100), 'product_data' => ['name' => 'Shipping']], 'quantity' => 1];
    }
}

if ($amountDollars <= 0) {
    jsonResponse(['success' => false, 'error' => 'Payment amount must be greater than $0.']);
}

$sessionOpts = [
    'order_id'       => $orderId,
    'customer_name'  => $order['customer_name'],
    'customer_email' => $order['email'],
    'amount_cents'   => (int)round($amountDollars * 100),
    'payment_type'   => $paymentType,
    'description'    => $description,
];
if (!empty($lineItems)) {
    $sessionOpts['line_items'] = $lineItems;
}

$result = createPortalPaymentSession($sessionOpts);

jsonResponse($result);
