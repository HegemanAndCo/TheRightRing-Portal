<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sheets.php';

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');
requireAdminLogin();

$orders = getAllOrders();
jsonResponse(['success' => true, 'orders' => $orders]);
