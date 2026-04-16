<?php
/**
 * Postgres-backed implementation of portal storage functions.
 * Mirrors sheets.php signatures so call sites are backend-agnostic (via store.php).
 * Active when $_ENV['STORAGE_BACKEND'] === 'postgres' and DATABASE_URL is set.
 */

date_default_timezone_set('America/New_York');

function getPortalDb(): ?PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $url = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?: '';
    if (!$url) return null;

    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) return null;

    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s',
        $parts['host'],
        $parts['port'] ?? 5432,
        ltrim($parts['path'] ?? '', '/')
    );

    try {
        $pdo = new PDO($dsn, $parts['user'] ?? '', $parts['pass'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (\Throwable $e) {
        error_log('Postgres connect failed: ' . $e->getMessage());
        return null;
    }
    return $pdo;
}

// ── USERS ────────────────────────────────────────────────────────────────────

function getUserByEmail(string $email): ?array {
    $db = getPortalDb();
    if (!$db) return null;
    try {
        $stmt = $db->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(:e) LIMIT 1');
        $stmt->execute([':e' => trim($email)]);
        $u = $stmt->fetch();
        if (!$u) return null;
        return [
            'row'           => (int)$u['id'],
            'email'         => $u['email'] ?? '',
            'phone_last4'   => $u['phone_last4'] ?? '',
            'password_hash' => $u['password_hash'] ?? '',
            'full_name'     => $u['full_name'] ?? '',
            'order_id'      => $u['order_id'] ?? '',
            'created_at'    => $u['created_at'] ?? '',
            'last_login'    => $u['last_login'] ?? '',
        ];
    } catch (\Throwable $e) {
        error_log('db getUserByEmail: ' . $e->getMessage());
        return null;
    }
}

function createUser(array $data): bool {
    $db = getPortalDb();
    if (!$db) return false;
    try {
        $stmt = $db->prepare('INSERT INTO users (email, phone_last4, password_hash, full_name, order_id)
            VALUES (:email, :phone, :hash, :name, :oid)
            ON CONFLICT (email) DO UPDATE SET
                phone_last4 = EXCLUDED.phone_last4,
                full_name   = EXCLUDED.full_name,
                order_id    = EXCLUDED.order_id');
        $stmt->execute([
            ':email' => $data['email'],
            ':phone' => $data['phone_last4'] ?? '',
            ':hash'  => $data['password_hash'] ?? '',
            ':name'  => $data['full_name'] ?? '',
            ':oid'   => $data['order_id'] ?? '',
        ]);
        return true;
    } catch (\Throwable $e) {
        error_log('db createUser: ' . $e->getMessage());
        return false;
    }
}

function updateUserPasswordHash(int $row, string $hash): bool {
    $db = getPortalDb();
    if (!$db) return false;
    try {
        $stmt = $db->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
        $stmt->execute([':h' => $hash, ':id' => $row]);
        return $stmt->rowCount() > 0;
    } catch (\Throwable $e) {
        error_log('db updateUserPasswordHash: ' . $e->getMessage());
        return false;
    }
}

function updateUserLastLogin(int $row): void {
    $db = getPortalDb();
    if (!$db) return;
    try {
        $stmt = $db->prepare('UPDATE users SET last_login = now() WHERE id = :id');
        $stmt->execute([':id' => $row]);
    } catch (\Throwable $e) {
        error_log('db updateUserLastLogin: ' . $e->getMessage());
    }
}

// ── ORDERS ───────────────────────────────────────────────────────────────────

function parseOrderRow(array $r, $rowNum = 0): array {
    return [
        'row'                        => $rowNum ?: ($r['order_id'] ?? ''),
        'order_id'                   => $r['order_id']             ?? '',
        'customer_name'              => $r['customer_name']        ?? '',
        'email'                      => $r['email']                ?? '',
        'phone'                      => $r['phone']                ?? '',
        'address'                    => $r['address']              ?? '',
        'ring_choices_json'          => $r['ring_choices_json']    ?? '[]',
        'status'                     => $r['status']               ?? 'Design Review',
        'timeline_note'              => $r['timeline_note']        ?? '',
        'estimated_completion'       => $r['estimated_completion'] ?? '',
        'project_update'             => $r['project_update']       ?? '',
        'total_estimate'             => (float)($r['total_estimate']       ?? 0),
        'deposit_paid'               => (float)($r['deposit_paid']         ?? 0),
        'progress_deposit_due'       => (float)($r['progress_deposit_due'] ?? 0),
        'final_payment_due'          => (float)($r['final_payment_due']    ?? 0),
        'final_payment_enabled'      => !empty($r['final_payment_enabled']) && $r['final_payment_enabled'] !== false && $r['final_payment_enabled'] !== 'f',
        'amount_paid_total'          => (float)($r['amount_paid_total']    ?? 0),
        'created_at'                 => $r['created_at']             ?? '',
        'updated_at'                 => $r['updated_at']             ?? '',
        'versions_json'              => $r['versions_json']          ?? '[]',
        'approved_version_id'        => $r['approved_version_id']    ?? '',
        'tracking_number'            => $r['tracking_number']        ?? '',
        'skip_resin_requested'       => $r['skip_resin_requested']   ?? '',
        'ring_approved_notification' => $r['ring_approved_notification'] ?? '',
        'facetime_requested'         => $r['facetime_requested']     ?? '',
        'care_plan_purchased'        => $r['care_plan_purchased']    ?? '',
        'care_plan_amount'           => $r['care_plan_amount']       ?? '',
        'charge_tax'                 => $r['charge_tax']             ?? '1',
        'estimate_json'              => $r['estimate_json']          ?? '',
        'shipping_charge'            => (float)($r['shipping_charge'] ?? 0),
    ];
}

function getOrderById(string $orderId): ?array {
    $db = getPortalDb();
    if (!$db) return null;
    try {
        $stmt = $db->prepare('SELECT * FROM orders WHERE order_id = :id LIMIT 1');
        $stmt->execute([':id' => trim($orderId)]);
        $r = $stmt->fetch();
        return $r ? parseOrderRow($r, $r['order_id']) : null;
    } catch (\Throwable $e) {
        error_log('db getOrderById: ' . $e->getMessage());
        return null;
    }
}

function getAllOrders(): array {
    $db = getPortalDb();
    if (!$db) return [];
    try {
        $rows = $db->query('SELECT * FROM orders ORDER BY created_at DESC')->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[] = parseOrderRow($r, $r['order_id']);
        }
        return $out;
    } catch (\Throwable $e) {
        error_log('db getAllOrders: ' . $e->getMessage());
        return [];
    }
}

function createOrder(array $data): bool {
    $db = getPortalDb();
    if (!$db) return false;
    $total     = (float)($data['total_estimate']    ?? 0);
    $deposit   = (float)($data['deposit_paid']      ?? 250);
    $paidTotal = (float)($data['amount_paid_total'] ?? $deposit);
    $progDue   = $total > 0 ? max(0, ($total / 2) - $deposit) : 0;

    try {
        $stmt = $db->prepare('INSERT INTO orders
            (order_id, customer_name, email, phone, address, ring_choices_json, status,
             total_estimate, deposit_paid, progress_deposit_due, amount_paid_total,
             versions_json, charge_tax)
            VALUES (:oid, :name, :email, :phone, :addr, :rcj, :status,
                    :te, :dp, :pdd, :apt, :vj, :ct)
            ON CONFLICT (order_id) DO NOTHING');
        $stmt->execute([
            ':oid'    => $data['order_id'],
            ':name'   => $data['customer_name'] ?? '',
            ':email'  => $data['email'] ?? '',
            ':phone'  => $data['phone'] ?? '',
            ':addr'   => $data['address'] ?? '',
            ':rcj'    => $data['ring_choices_json'] ?? '[]',
            ':status' => 'Design Review',
            ':te'     => $total,
            ':dp'     => $deposit,
            ':pdd'    => $progDue,
            ':apt'    => $paidTotal,
            ':vj'     => '[]',
            ':ct'     => '1',
        ]);
        return true;
    } catch (\Throwable $e) {
        error_log('db createOrder: ' . $e->getMessage());
        return false;
    }
}

function updateOrder($row, array $data): bool {
    // $row is the order_id in Postgres mode (parseOrderRow sets it that way).
    $db = getPortalDb();
    if (!$db) return false;
    $orderId = is_string($row) ? $row : ($data['order_id'] ?? '');
    if (!$orderId) return false;

    $total   = (float)($data['total_estimate'] ?? 0);
    $deposit = (float)($data['deposit_paid']   ?? 0);
    $progDue = isset($data['progress_deposit_due'])
        ? (float)$data['progress_deposit_due']
        : max(0, ($total / 2) - $deposit);

    try {
        $stmt = $db->prepare('UPDATE orders SET
            customer_name = :name,
            email = :email,
            phone = :phone,
            address = :addr,
            ring_choices_json = :rcj,
            status = :status,
            timeline_note = :tn,
            estimated_completion = :ec,
            project_update = :pu,
            total_estimate = :te,
            deposit_paid = :dp,
            progress_deposit_due = :pdd,
            final_payment_due = :fpd,
            final_payment_enabled = :fpe,
            amount_paid_total = :apt,
            versions_json = :vj,
            approved_version_id = :avi,
            tracking_number = :tnum,
            skip_resin_requested = :srr,
            ring_approved_notification = :ran,
            facetime_requested = :ftr,
            care_plan_purchased = :cpp,
            care_plan_amount = :cpa,
            charge_tax = :ct,
            estimate_json = :ej,
            shipping_charge = :sc,
            updated_at = now()
            WHERE order_id = :oid');
        $stmt->execute([
            ':oid'    => $orderId,
            ':name'   => $data['customer_name']        ?? '',
            ':email'  => $data['email']                ?? '',
            ':phone'  => $data['phone']                ?? '',
            ':addr'   => $data['address']              ?? '',
            ':rcj'    => $data['ring_choices_json']    ?? '[]',
            ':status' => $data['status']               ?? 'Design Review',
            ':tn'     => $data['timeline_note']        ?? '',
            ':ec'     => $data['estimated_completion'] ?? '',
            ':pu'     => $data['project_update']       ?? '',
            ':te'     => $total,
            ':dp'     => (float)($data['deposit_paid']      ?? 500),
            ':pdd'    => $progDue,
            ':fpd'    => (float)($data['final_payment_due'] ?? 0),
            ':fpe'    => !empty($data['final_payment_enabled']),
            ':apt'    => (float)($data['amount_paid_total'] ?? 500),
            ':vj'     => $data['versions_json']       ?? '[]',
            ':avi'    => $data['approved_version_id'] ?? '',
            ':tnum'   => $data['tracking_number']     ?? '',
            ':srr'    => $data['skip_resin_requested']       ?? '',
            ':ran'    => $data['ring_approved_notification'] ?? '',
            ':ftr'    => $data['facetime_requested']         ?? '',
            ':cpp'    => $data['care_plan_purchased']        ?? '',
            ':cpa'    => $data['care_plan_amount']           ?? '',
            ':ct'     => $data['charge_tax']                 ?? '1',
            ':ej'     => $data['estimate_json']              ?? '',
            ':sc'     => (float)($data['shipping_charge']    ?? 0),
        ]);
        return $stmt->rowCount() > 0;
    } catch (\Throwable $e) {
        error_log('db updateOrder: ' . $e->getMessage());
        return false;
    }
}

function markPaymentReceived(string $orderId, float $amount, string $type): bool {
    $db = getPortalDb();
    if (!$db) return false;
    $order = getOrderById($orderId);
    if (!$order) return false;

    try {
        if ($type === 'deposit') {
            $stmt = $db->prepare('UPDATE orders SET
                amount_paid_total = amount_paid_total + :a,
                deposit_paid = :a,
                updated_at = now()
                WHERE order_id = :o');
            $stmt->execute([':a' => $amount, ':o' => $orderId]);
        } elseif ($type === 'progress') {
            $stmt = $db->prepare('UPDATE orders SET
                amount_paid_total = amount_paid_total + :a,
                progress_deposit_due = 0,
                updated_at = now()
                WHERE order_id = :o');
            $stmt->execute([':a' => $amount, ':o' => $orderId]);
        } elseif ($type === 'final') {
            $clearShipping = ((float)$order['shipping_charge']) > 0;
            $stmt = $db->prepare('UPDATE orders SET
                amount_paid_total = amount_paid_total + :a,
                final_payment_due = 0,
                status = :s,
                shipping_charge = CASE WHEN :cs THEN 0 ELSE shipping_charge END,
                updated_at = now()
                WHERE order_id = :o');
            $stmt->execute([
                ':a'  => $amount,
                ':s'  => 'Complete and Ready for Delivery',
                ':cs' => $clearShipping,
                ':o'  => $orderId,
            ]);
        } elseif ($type === 'care-plan') {
            // Care plan price is NOT added to amount_paid_total (separate product)
            $stmt = $db->prepare('UPDATE orders SET
                care_plan_purchased = :p,
                care_plan_amount = :amt,
                status = :s,
                updated_at = now()
                WHERE order_id = :o');
            $stmt->execute([
                ':p'   => '1',
                ':amt' => (string)$amount,
                ':s'   => 'Complete and Ready for Delivery',
                ':o'   => $orderId,
            ]);
        } elseif ($type === 'final-with-care-plan') {
            $clearShipping = ((float)$order['shipping_charge']) > 0;
            $stmt = $db->prepare('UPDATE orders SET
                amount_paid_total = amount_paid_total + :a,
                final_payment_due = 0,
                care_plan_purchased = :p,
                care_plan_amount = :amt,
                status = :s,
                shipping_charge = CASE WHEN :cs THEN 0 ELSE shipping_charge END,
                updated_at = now()
                WHERE order_id = :o');
            $stmt->execute([
                ':a'   => $amount,
                ':p'   => '1',
                ':amt' => (string)$amount,
                ':s'   => 'Complete and Ready for Delivery',
                ':cs'  => $clearShipping,
                ':o'   => $orderId,
            ]);
        } else {
            $stmt = $db->prepare('UPDATE orders SET
                amount_paid_total = amount_paid_total + :a,
                updated_at = now()
                WHERE order_id = :o');
            $stmt->execute([':a' => $amount, ':o' => $orderId]);
        }
        return true;
    } catch (\Throwable $e) {
        error_log('db markPaymentReceived: ' . $e->getMessage());
        return false;
    }
}

// ── MEDIA ────────────────────────────────────────────────────────────────────

function getMediaForOrder(string $orderId): array {
    $db = getPortalDb();
    if (!$db) return [];
    try {
        $stmt = $db->prepare('SELECT * FROM media WHERE order_id = :o AND deleted = false ORDER BY uploaded_at ASC');
        $stmt->execute([':o' => $orderId]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[] = [
                'media_id'      => $r['media_id']      ?? '',
                'order_id'      => $r['order_id']      ?? '',
                'uploader'      => $r['uploader']      ?? '',
                'filename'      => $r['filename']      ?? '',
                'drive_file_id' => $r['drive_file_id'] ?? '',
                'thumbnail_url' => $r['thumbnail_url'] ?? '',
                'uploaded_at'   => $r['uploaded_at']   ?? '',
                'caption'       => $r['caption']       ?? '',
            ];
        }
        return $out;
    } catch (\Throwable $e) {
        error_log('db getMediaForOrder: ' . $e->getMessage());
        return [];
    }
}

function addMediaRecord(array $data): bool {
    $db = getPortalDb();
    if (!$db) return false;
    try {
        $stmt = $db->prepare('INSERT INTO media
            (media_id, order_id, uploader, filename, drive_file_id, thumbnail_url, caption)
            VALUES (:mid, :oid, :up, :fn, :dfi, :tu, :cap)
            ON CONFLICT (media_id) DO NOTHING');
        $stmt->execute([
            ':mid' => $data['media_id'],
            ':oid' => $data['order_id'],
            ':up'  => $data['uploader'] ?? '',
            ':fn'  => $data['filename'] ?? '',
            ':dfi' => $data['drive_file_id'] ?? '',
            ':tu'  => $data['thumbnail_url'] ?? '',
            ':cap' => $data['caption'] ?? '',
        ]);
        return true;
    } catch (\Throwable $e) {
        error_log('db addMediaRecord: ' . $e->getMessage());
        return false;
    }
}

function deleteMediaRecord(string $mediaId, string $orderId): array|false {
    $db = getPortalDb();
    if (!$db) return false;
    try {
        $sel = $db->prepare('SELECT thumbnail_url FROM media WHERE media_id = :m AND order_id = :o AND deleted = false');
        $sel->execute([':m' => $mediaId, ':o' => $orderId]);
        $r = $sel->fetch();
        if (!$r) return false;

        $upd = $db->prepare('UPDATE media SET deleted = true WHERE media_id = :m AND order_id = :o');
        $upd->execute([':m' => $mediaId, ':o' => $orderId]);
        return ['thumbnail_url' => $r['thumbnail_url'] ?? ''];
    } catch (\Throwable $e) {
        error_log('db deleteMediaRecord: ' . $e->getMessage());
        return false;
    }
}

function updateMediaCaption(string $mediaId, string $orderId, string $caption): bool {
    $db = getPortalDb();
    if (!$db) return false;
    try {
        $stmt = $db->prepare('UPDATE media SET caption = :c WHERE media_id = :m AND order_id = :o AND deleted = false');
        $stmt->execute([':c' => $caption, ':m' => $mediaId, ':o' => $orderId]);
        return $stmt->rowCount() > 0;
    } catch (\Throwable $e) {
        error_log('db updateMediaCaption: ' . $e->getMessage());
        return false;
    }
}

// ── TOKENS ───────────────────────────────────────────────────────────────────

function createMagicLinkToken(string $email): string {
    $db = getPortalDb();
    if (!$db) return '';
    $token = bin2hex(random_bytes(32));
    try {
        $stmt = $db->prepare("INSERT INTO tokens (token, email, expires_at, used)
            VALUES (:t, :e, now() + interval '48 hours', false)");
        $stmt->execute([':t' => $token, ':e' => $email]);
        return $token;
    } catch (\Throwable $e) {
        error_log('db createMagicLinkToken: ' . $e->getMessage());
        return '';
    }
}

function getMagicLinkToken(string $token): ?array {
    $db = getPortalDb();
    if (!$db) return null;
    try {
        $stmt = $db->prepare('SELECT * FROM tokens WHERE token = :t LIMIT 1');
        $stmt->execute([':t' => trim($token)]);
        $r = $stmt->fetch();
        if (!$r) return null;
        return [
            'row'        => $r['token'],
            'token'      => $r['token'] ?? '',
            'email'      => $r['email'] ?? '',
            'expires_at' => $r['expires_at'] ?? '',
            'used'       => (!empty($r['used']) && $r['used'] !== 'f') ? '1' : '0',
        ];
    } catch (\Throwable $e) {
        error_log('db getMagicLinkToken: ' . $e->getMessage());
        return null;
    }
}

function markMagicLinkTokenUsed($row): void {
    $db = getPortalDb();
    if (!$db) return;
    try {
        // $row is the token string in Postgres mode
        $stmt = $db->prepare('UPDATE tokens SET used = true WHERE token = :t');
        $stmt->execute([':t' => (string)$row]);
    } catch (\Throwable $e) {
        error_log('db markMagicLinkTokenUsed: ' . $e->getMessage());
    }
}
