<?php
/**
 * Bootstrap: resolves vendor autoload and env loading for both Railway and EC2.
 *
 * Railway: WORKDIR=/app, vendor/ is at /app/vendor/ (built by Dockerfile Composer install)
 * EC2:     portal lives at /var/www/html/portal/, vendor/ at /var/www/html/TheRightRing/vendor/
 */

// Resolve vendor autoload path
if (file_exists('/app/vendor/autoload.php')) {
    // Railway environment
    require_once '/app/vendor/autoload.php';
    $dotenvPath = '/app';
} else {
    // EC2 environment — vendor is in sibling TheRightRing directory
    require_once __DIR__ . '/../TheRightRing/vendor/autoload.php';
    $dotenvPath = __DIR__ . '/../TheRightRing';
}

// Load .env (safeLoad won't throw if file doesn't exist)
$dotenv = Dotenv\Dotenv::createImmutable($dotenvPath);
$dotenv->safeLoad();

// Resolve google credentials path
define('GOOGLE_CREDS_PATH',
    file_exists('/app/Portal/google-credentials.json')
        ? '/app/Portal/google-credentials.json'
        : __DIR__ . '/../TheRightRing/Portal/google-credentials.json'
);

// Resolve uploads path
define('UPLOADS_PATH',
    is_dir('/app/uploads')
        ? '/app/uploads'
        : __DIR__ . '/uploads'
);
