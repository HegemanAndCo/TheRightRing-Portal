<?php
/**
 * One-time migration: copies users/orders/media/tokens from Google Sheets into Postgres.
 * Idempotent — safe to re-run; uses ON CONFLICT DO NOTHING.
 *
 * Run on Railway: `railway run php scripts/migrate_sheets_to_postgres.php`
 * Requires: DATABASE_URL, PORTAL_SHEET_ID, google-credentials.json all available.
 */

require_once __DIR__ . '/../bootstrap.php';

// ─── Postgres connection ─────────────────────────────────────────────────────
$dbUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?: '';
if (!$dbUrl) {
    fwrite(STDERR, "DATABASE_URL not set.\n");
    exit(1);
}
$parts = parse_url($dbUrl);
$dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s',
    $parts['host'], $parts['port'] ?? 5432, ltrim($parts['path'] ?? '', '/'));
$pdo = new PDO($dsn, $parts['user'] ?? '', $parts['pass'] ?? '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// ─── Google Sheets client ────────────────────────────────────────────────────
$credPath = defined('GOOGLE_CREDS_PATH') ? GOOGLE_CREDS_PATH : '';
$sheetId = $_ENV['PORTAL_SHEET_ID'] ?? getenv('PORTAL_SHEET_ID') ?: '';
if (!$credPath || !file_exists($credPath) || !$sheetId) {
    fwrite(STDERR, "Missing google-credentials.json or PORTAL_SHEET_ID.\n");
    exit(1);
}
$client = new Google_Client();
$client->setApplicationName('TRR Migration');
$client->setScopes([Google_Service_Sheets::SPREADSHEETS_READONLY]);
$client->setAuthConfig($credPath);
$svc = new Google_Service_Sheets($client);

function fetchRows(Google_Service_Sheets $svc, string $sheetId, string $range): array {
    try {
        return $svc->spreadsheets_values->get($sheetId, $range)->getValues() ?? [];
    } catch (\Throwable $e) {
        fwrite(STDERR, "Fetch $range failed: " . $e->getMessage() . "\n");
        return [];
    }
}

function num(?string $s): float { return (float)($s ?? 0); }
function bool01(?string $s): bool { return ($s ?? '') === '1'; }

$counts = ['users' => 0, 'orders' => 0, 'media' => 0, 'tokens' => 0];

// ─── USERS ───────────────────────────────────────────────────────────────────
echo "Migrating users...\n";
$rows = fetchRows($svc, $sheetId, 'users!A2:G');
$stmt = $pdo->prepare('INSERT INTO users (email, phone_last4, password_hash, full_name, order_id, created_at, last_login)
    VALUES (:e, :p, :h, :n, :o, :c, :l)
    ON CONFLICT (email) DO NOTHING');
foreach ($rows as $r) {
    if (empty($r[0])) continue;
    $stmt->execute([
        ':e' => trim($r[0]),
        ':p' => $r[1] ?? '',
        ':h' => $r[2] ?? '',
        ':n' => $r[3] ?? '',
        ':o' => $r[4] ?? '',
        ':c' => !empty($r[5]) ? $r[5] : null,
        ':l' => !empty($r[6]) ? $r[6] : null,
    ]);
    $counts['users']++;
}

// ─── ORDERS ──────────────────────────────────────────────────────────────────
echo "Migrating orders...\n";
$rows = fetchRows($svc, $sheetId, 'orders!A2:AC2000');
$stmt = $pdo->prepare('INSERT INTO orders (
    order_id, customer_name, email, phone, address, ring_choices_json, status,
    timeline_note, estimated_completion, project_update,
    total_estimate, deposit_paid, progress_deposit_due, final_payment_due, final_payment_enabled,
    amount_paid_total, created_at, updated_at,
    versions_json, approved_version_id, tracking_number,
    skip_resin_requested, ring_approved_notification, facetime_requested,
    care_plan_purchased, care_plan_amount, charge_tax, estimate_json, shipping_charge)
    VALUES (:oid, :cn, :em, :ph, :ad, :rcj, :st,
            :tn, :ec, :pu,
            :te, :dp, :pdd, :fpd, :fpe,
            :apt, :ca, :ua,
            :vj, :avi, :trk,
            :srr, :ran, :ftr,
            :cpp, :cpa, :ct, :ej, :sc)
    ON CONFLICT (order_id) DO NOTHING');
foreach ($rows as $r) {
    if (empty($r[0])) continue;
    $stmt->execute([
        ':oid' => $r[0], ':cn'  => $r[1]  ?? '', ':em'  => $r[2]  ?? '',
        ':ph'  => $r[3]  ?? '', ':ad'  => $r[4]  ?? '', ':rcj' => $r[5] ?? '[]',
        ':st'  => $r[6]  ?? 'Design Review',
        ':tn'  => $r[7]  ?? '', ':ec'  => $r[8]  ?? '', ':pu'  => $r[9]  ?? '',
        ':te'  => num($r[10] ?? null), ':dp'  => num($r[11] ?? null),
        ':pdd' => num($r[12] ?? null), ':fpd' => num($r[13] ?? null),
        ':fpe' => bool01($r[14] ?? null),
        ':apt' => num($r[15] ?? null),
        ':ca'  => !empty($r[16]) ? $r[16] : null,
        ':ua'  => !empty($r[17]) ? $r[17] : null,
        ':vj'  => $r[18] ?? '[]',
        ':avi' => $r[19] ?? '', ':trk' => $r[20] ?? '',
        ':srr' => $r[21] ?? '', ':ran' => $r[22] ?? '', ':ftr' => $r[23] ?? '',
        ':cpp' => $r[24] ?? '', ':cpa' => $r[25] ?? '',
        ':ct'  => $r[26] ?? '1', ':ej'  => $r[27] ?? '',
        ':sc'  => num($r[28] ?? null),
    ]);
    $counts['orders']++;
}

// ─── MEDIA ───────────────────────────────────────────────────────────────────
echo "Migrating media...\n";
$rows = fetchRows($svc, $sheetId, 'media!A2:H');
$stmt = $pdo->prepare('INSERT INTO media
    (media_id, order_id, uploader, filename, drive_file_id, thumbnail_url, uploaded_at, caption)
    VALUES (:m, :o, :u, :f, :d, :t, :ua, :c)
    ON CONFLICT (media_id) DO NOTHING');
foreach ($rows as $r) {
    if (empty($r[0])) continue;
    $stmt->execute([
        ':m'  => $r[0],
        ':o'  => $r[1] ?? '',
        ':u'  => $r[2] ?? '',
        ':f'  => $r[3] ?? '',
        ':d'  => $r[4] ?? '',
        ':t'  => $r[5] ?? '',
        ':ua' => !empty($r[6]) ? $r[6] : null,
        ':c'  => $r[7] ?? '',
    ]);
    $counts['media']++;
}

// ─── TOKENS ──────────────────────────────────────────────────────────────────
echo "Migrating tokens...\n";
$rows = fetchRows($svc, $sheetId, 'tokens!A2:D');
$stmt = $pdo->prepare('INSERT INTO tokens (token, email, expires_at, used)
    VALUES (:t, :e, :x, :u)
    ON CONFLICT (token) DO NOTHING');
foreach ($rows as $r) {
    if (empty($r[0])) continue;
    $stmt->execute([
        ':t' => $r[0],
        ':e' => $r[1] ?? '',
        ':x' => !empty($r[2]) ? $r[2] : date('Y-m-d H:i:s', strtotime('+48 hours')),
        ':u' => bool01($r[3] ?? null),
    ]);
    $counts['tokens']++;
}

echo "Migration complete:\n";
foreach ($counts as $t => $c) echo "  $t: $c rows\n";
