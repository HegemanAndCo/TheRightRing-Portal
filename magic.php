<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/sheets.php';
require_once __DIR__ . '/bootstrap.php';

$token = trim($_GET['token'] ?? '');

if (empty($token)) {
    header('Location: /index.php?error=invalid_link');
    exit();
}

$record = getMagicLinkToken($token);

if (!$record) {
    header('Location: /index.php?error=invalid_link');
    exit();
}

if ($record['used'] === '1') {
    header('Location: /index.php?error=link_used');
    exit();
}

if (strtotime($record['expires_at']) < time()) {
    header('Location: /index.php?error=link_expired');
    exit();
}

// Mark token used immediately
markMagicLinkTokenUsed($record['row']);

// Store email in session so set-password page can use it
portalSessionStart();
$_SESSION['magic_link_email'] = $record['email'];

header('Location: /index.php?mode=set_password');
exit();
