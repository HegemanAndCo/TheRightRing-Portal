<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/store.php';

// Load .env
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = strtolower(trim($input['email'] ?? ''));
$mode  = $input['mode'] ?? 'initial'; // 'initial' = email+phone4, 'password' = email+password

if (empty($email)) {
    jsonResponse(['success' => false, 'error' => 'Email is required']);
}

$user = getUserByEmail($email);

if (!$user) {
    jsonResponse(['success' => false, 'error' => 'No account found for that email. Please submit your ring design first.']);
}

if ($mode === 'initial') {
    // First-time or password-reset: verify email + last 4 of phone
    $phone4 = trim($input['phone_last4'] ?? '');
    if (empty($phone4) || strlen($phone4) !== 4) {
        jsonResponse(['success' => false, 'error' => 'Please enter the last 4 digits of your phone number.']);
    }

    // Normalize both sides: strip leading zeros so "0111" matches "111"
    if (ltrim($user['phone_last4'], '0') !== ltrim($phone4, '0')) {
        jsonResponse(['success' => false, 'error' => 'Phone number does not match our records.']);
    }

    // If no password set yet, tell client to show set-password form
    if (empty($user['password_hash'])) {
        jsonResponse(['success' => true, 'next' => 'set_password', 'email' => $email]);
    }

    // Phone matches and password exists — log them in
    loginCustomer($email, $user['order_id']);
    updateUserLastLogin($user['row']);
    jsonResponse(['success' => true, 'next' => 'dashboard']);

} elseif ($mode === 'password') {
    $password = $input['password'] ?? '';
    if (empty($password)) {
        jsonResponse(['success' => false, 'error' => 'Password is required.']);
    }

    if (empty($user['password_hash']) || !verifyPassword($password, $user['password_hash'])) {
        jsonResponse(['success' => false, 'error' => 'Incorrect password. Use email + last 4 digits of phone to reset.']);
    }

    loginCustomer($email, $user['order_id']);
    updateUserLastLogin($user['row']);
    jsonResponse(['success' => true, 'next' => 'dashboard']);

} else {
    jsonResponse(['success' => false, 'error' => 'Invalid mode.']);
}
