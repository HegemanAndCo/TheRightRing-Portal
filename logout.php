<?php
require_once __DIR__ . '/lib/auth.php';
$wasAdmin = isLoggedInAsAdmin();
portalLogout();
if ($wasAdmin) {
    header('Location: /admin.php');
} else {
    $redirect = $_GET['redirect'] ?? '';
    if ($redirect === 'change_password') {
        header('Location: /index.php?tab=initial');
    } else {
        header('Location: /index.php?tab=password');
    }
}
exit();
