<?php
/**
 * Auth helpers: session management, password hashing, access guards.
 */

function portalSessionStart(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('portal_session');
        session_start();
    }
}

function requireCustomerLogin(): void {
    portalSessionStart();
    if (empty($_SESSION['portal_user_email'])) {
        header('Location: /index.php');
        exit();
    }
}

function requireAdminLogin(): void {
    portalSessionStart();
    if (empty($_SESSION['portal_is_admin'])) {
        $isApi = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Session expired. Please log in again.']);
            exit();
        }
        header('Location: /admin.php');
        exit();
    }
}

function isLoggedInAsCustomer(): bool {
    portalSessionStart();
    return !empty($_SESSION['portal_user_email']);
}

function isLoggedInAsAdmin(): bool {
    portalSessionStart();
    return !empty($_SESSION['portal_is_admin']);
}

function loginCustomer(string $email, string $orderId): void {
    portalSessionStart();
    $_SESSION['portal_user_email'] = $email;
    $_SESSION['portal_order_id']   = $orderId;
    $_SESSION['portal_is_admin']   = false;
}

function loginAdmin(string $role = 'full'): void {
    portalSessionStart();
    $_SESSION['portal_is_admin']   = true;
    $_SESSION['portal_admin_role'] = $role; // 'full' | 'limited'
    $_SESSION['portal_user_email'] = null;
}

function isFullAdmin(): bool {
    portalSessionStart();
    return !empty($_SESSION['portal_is_admin']) && ($_SESSION['portal_admin_role'] ?? 'full') === 'full';
}

function getAdminRole(): string {
    portalSessionStart();
    return $_SESSION['portal_admin_role'] ?? 'full';
}

function portalLogout(): void {
    portalSessionStart();
    session_destroy();
}

function getCurrentUserEmail(): string {
    return $_SESSION['portal_user_email'] ?? '';
}

function getCurrentOrderId(): string {
    return $_SESSION['portal_order_id'] ?? '';
}

function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}
