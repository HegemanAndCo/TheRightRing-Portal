<?php
/**
 * Storage backend dispatcher.
 * Loads db.php (Postgres) when STORAGE_BACKEND=postgres, otherwise sheets.php.
 * Both implementations expose the same function signatures, so call sites
 * just require_once this file instead of sheets.php directly.
 */

$_backend = strtolower($_ENV['STORAGE_BACKEND'] ?? getenv('STORAGE_BACKEND') ?: '');

if ($_backend === 'postgres' || $_backend === 'pgsql') {
    require_once __DIR__ . '/db.php';
    // drive.php is still needed for Google Drive uploads in Postgres mode.
    require_once __DIR__ . '/drive.php';
} else {
    require_once __DIR__ . '/sheets.php';
    require_once __DIR__ . '/drive.php';
}
