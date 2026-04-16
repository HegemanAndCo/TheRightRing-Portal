<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/store.php';

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$input      = json_decode(file_get_contents('php://input'), true);
$email      = strtolower(trim($input['email'] ?? ''));
$password   = $input['password'] ?? '';
$confirm    = $input['confirm']  ?? '';
$tokenMode  = !empty($input['token_mode']); // true when coming from magic link

if (empty($email) || empty($password)) {
    jsonResponse(['success' => false, 'error' => 'All fields are required.']);
}

if ($password !== $confirm) {
    jsonResponse(['success' => false, 'error' => 'Passwords do not match.']);
}

if (strlen($password) < 8) {
    jsonResponse(['success' => false, 'error' => 'Password must be at least 8 characters.']);
}

$user = getUserByEmail($email);
if (!$user) {
    jsonResponse(['success' => false, 'error' => 'Account not found.']);
}

if (!$tokenMode) {
    // Legacy: verify phone4
    $phone4 = trim($input['phone_last4'] ?? '');
    if (ltrim($user['phone_last4'], '0') !== ltrim($phone4, '0')) {
        jsonResponse(['success' => false, 'error' => 'Phone number does not match.']);
    }
} else {
    // Token mode: verify the session magic_link_email matches
    portalSessionStart();
    $sessionEmail = strtolower(trim($_SESSION['magic_link_email'] ?? ''));
    if ($sessionEmail !== $email) {
        jsonResponse(['success' => false, 'error' => 'Session mismatch. Please use your setup link again.']);
    }
    // Clear the magic link session var
    unset($_SESSION['magic_link_email']);
}

$hash = hashPassword($password);
$ok   = updateUserPasswordHash($user['row'], $hash);

if (!$ok) {
    jsonResponse(['success' => false, 'error' => 'Could not save password. Please try again.']);
}

loginCustomer($email, $user['order_id']);
updateUserLastLogin($user['row']);
jsonResponse(['success' => true, 'next' => 'dashboard']);
