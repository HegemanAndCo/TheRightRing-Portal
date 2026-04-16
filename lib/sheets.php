<?php
/**
 * Google Sheets helpers for the portal.
 * Falls back gracefully if credentials don't exist.
 */

date_default_timezone_set('America/New_York');

// Autoload loaded via bootstrap.php

function getPortalSheetsService() {
    $credPath = GOOGLE_CREDS_PATH;
    $sheetId  = $_ENV['PORTAL_SHEET_ID'] ?? '';

    if (!file_exists($credPath) || empty($sheetId)) {
        return null;
    }

    $client = new \Google_Client();
    $client->setApplicationName('The Right Ring Portal');
    $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
    $client->setAuthConfig($credPath);
    $client->setAccessType('offline');
    $client->setHttpClient(new \GuzzleHttp\Client(['connect_timeout' => 10, 'timeout' => 20]));

    return new \Google_Service_Sheets($client);
}

// ── USERS sheet ──────────────────────────────────────────────────────────────

function getUserByEmail(string $email): ?array {
    $svc = getPortalSheetsService();
    if (!$svc) return null;
    $sheetId = $_ENV['PORTAL_SHEET_ID'];

    try {
        $response = $svc->spreadsheets_values->get($sheetId, 'users!A2:G');
        $rows = $response->getValues() ?? [];
        foreach ($rows as $i => $row) {
            if (strtolower(trim($row[0] ?? '')) === strtolower(trim($email))) {
                return [
                    'row'          => $i + 2,
                    'email'        => $row[0] ?? '',
                    'phone_last4'  => $row[1] ?? '',
                    'password_hash'=> $row[2] ?? '',
                    'full_name'    => $row[3] ?? '',
                    'order_id'     => $row[4] ?? '',
                    'created_at'   => $row[5] ?? '',
                    'last_login'   => $row[6] ?? '',
                ];
            }
        }
    } catch (\Exception $e) {
        error_log("Sheets getUserByEmail error: " . $e->getMessage());
    }
    return null;
}

function createUser(array $data): bool {
    $svc = getPortalSheetsService();
    if (!$svc) return false;
    $sheetId = $_ENV['PORTAL_SHEET_ID'];

    $values = [[
        $data['email'],
        $data['phone_last4'],        // stored as plain text via RAW mode
        $data['password_hash'] ?? '',
        $data['full_name'],
        $data['order_id'],
        date('Y-m-d H:i:s'),
        '',
    ]];

    try {
        $body = new \Google_Service_Sheets_ValueRange(['values' => $values]);
        $svc->spreadsheets_values->append($sheetId, 'users!A:G', $body, ['valueInputOption' => 'RAW']);
        return true;
    } catch (\Exception $e) {
        error_log("Sheets createUser error: " . $e->getMessage());
        return false;
    }
}

function updateUserPasswordHash(int $row, string $hash): bool {
    $svc = getPortalSheetsService();
    if (!$svc) return false;
    $sheetId = $_ENV['PORTAL_SHEET_ID'];

    try {
        $body = new \Google_Service_Sheets_ValueRange(['values' => [[$hash]]]);
        $svc->spreadsheets_values->update($sheetId, "users!C{$row}", $body, ['valueInputOption' => 'USER_ENTERED']);
        return true;
    } catch (\Exception $e) {
        error_log("Sheets updateUserPasswordHash error: " . $e->getMessage());
        return false;
    }
}

function updateUserLastLogin(int $row): void {
    $svc = getPortalSheetsService();
    if (!$svc) return;
    $sheetId = $_ENV['PORTAL_SHEET_ID'];

    try {
        $body = new \Google_Service_Sheets_ValueRange(['values' => [[date('Y-m-d H:i:s')]]]);
        $svc->spreadsheets_values->update($sheetId, "users!G{$row}", $body, ['valueInputOption' => 'USER_ENTERED']);
    } catch (\Exception $e) {
        error_log("Sheets updateUserLastLogin error: " . $e->getMessage());
    }
}

// ── ORDERS sheet ─────────────────────────────────────────────────────────────

function getOrderById(string $orderId): ?array {
    $svc = getPortalSheetsService();
    if (!$svc) return null;
    $sheetId = $_ENV['PORTAL_SHEET_ID'];

    try {
        $response = $svc->spreadsheets_values->get($sheetId, 'orders!A2:AC2000');
        $rows = $response->getValues() ?? [];
        foreach ($rows as $i => $row) {
            if (trim($row[0] ?? '') === trim($orderId)) {
                return parseOrderRow($row, $i + 2);
            }
        }
    } catch (\Exception $e) {
        error_log("Sheets getOrderById error: " . $e->getMessage());
    }
    return null;
}

function getAllOrders(): array {
    $svc = getPortalSheetsService();
    if (!$svc) return [];
    $sheetId = $_ENV['PORTAL_SHEET_ID'];

    try {
        $response = $svc->spreadsheets_values->get($sheetId, 'orders!A2:AC2000');
        $rows = $response->getValues() ?? [];
        $result = [];
        foreach ($rows as $i => $row) {
            if (!empty($row[0])) {
                $result[] = parseOrderRow($row, $i + 2);
            }
        }
        return $result;
    } catch (\Exception $e) {
        error_log("Sheets getAllOrders error: " . $e->getMessage());
        return [];
    }
}

function parseOrderRow(array $row, int $rowNum): array {
    return [
        'row'                   => $rowNum,
        'order_id'              => $row[0]  ?? '',
        'customer_name'         => $row[1]  ?? '',
        'email'                 => $row[2]  ?? '',
        'phone'                 => $row[3]  ?? '',
        'address'               => $row[4]  ?? '',
        'ring_choices_json'     => $row[5]  ?? '[]',
        'status'                => $row[6]  ?? 'Design Review',
        'timeline_note'         => $row[7]  ?? '',
        'estimated_completion'  => $row[8]  ?? '',
        'project_update'        => $row[9]  ?? '',
        'total_estimate'        => (float)($row[10] ?? 0),
        'deposit_paid'          => (float)($row[11] ?? 500),
        'progress_deposit_due'  => (float)($row[12] ?? 0),
        'final_payment_due'     => (float)($row[13] ?? 0),
        'final_payment_enabled' => ($row[14] ?? '0') === '1',
        'amount_paid_total'     => (float)($row[15] ?? 500),
        'created_at'            => $row[16] ?? '',
        'updated_at'            => $row[17] ?? '',
        'versions_json'         => $row[18] ?? '[]',
        'approved_version_id'   => $row[19] ?? '',
        'tracking_number'       => $row[20] ?? '',
        'skip_resin_requested'     => $row[21] ?? '',
        'ring_approved_notification' => $row[22] ?? '',
        'facetime_requested'         => $row[23] ?? '',
        'care_plan_purchased'        => $row[24] ?? '',
        'care_plan_amount'           => $row[25] ?? '',
        'charge_tax'                 => $row[26] ?? '1',
        'estimate_json'              => $row[27] ?? '',
        'shipping_charge'            => (float)($row[28] ?? 0),
    ];
}

function createOrder(array $data): bool {
    $svc = getPortalSheetsService();
    if (!$svc) return false;
    $sheetId = $_ENV['PORTAL_SHEET_ID'];

    $total      = (float)($data['total_estimate'] ?? 0);
    $deposit    = (float)($data['deposit_paid'] ?? 250);
    $paidTotal  = (float)($data['amount_paid_total'] ?? $deposit);
    $progDue    = $total > 0 ? max(0, ($total / 2) - $deposit) : 0;

    $values = [[
        $data['order_id'],
        $data['customer_name'],
        $data['email'],
        $data['phone'],
        $data['address'] ?? '',
        $data['ring_choices_json'] ?? '[]',
        'Design Review',
        '', '', '',
        (string)$total,
        (string)$deposit,
        (string)$progDue,
        '', '0', (string)$paidTotal,
        date('Y-m-d H:i:s'),
        date('Y-m-d H:i:s'),
        '[]',
        '',
        '',
        '',
        '',
        '1', // charge_tax — default on
    ]];

    try {
        $body = new \Google_Service_Sheets_ValueRange(['values' => $values]);
        $svc->spreadsheets_values->append($sheetId, 'orders!A:A', $body, ['valueInputOption' => 'USER_ENTERED', 'insertDataOption' => 'INSERT_ROWS']);
        return true;
    } catch (\Exception $e) {
        error_log("Sheets createOrder error: " . $e->getMessage());
        return false;
    }
}

function updateOrder(int $row, array $data): bool {
    $svc = getPortalSheetsService();
    if (!$svc) return false;
    $sheetId = $_ENV['PORTAL_SHEET_ID'];

    $total   = (float)($data['total_estimate'] ?? 0);
    $deposit = (float)($data['deposit_paid'] ?? 0);
    $progDue = isset($data['progress_deposit_due'])
        ? (float)$data['progress_deposit_due']
        : max(0, ($total / 2) - $deposit);

    $values = [[
        $data['order_id']             ?? '',
        $data['customer_name']        ?? '',
        $data['email']                ?? '',
        $data['phone']                ?? '',
        $data['address']              ?? '',
        $data['ring_choices_json']    ?? '[]',
        $data['status']               ?? 'Design Review',
        $data['timeline_note']        ?? '',
        $data['estimated_completion'] ?? '',
        $data['project_update']       ?? '',
        (string)$total,
        (string)($data['deposit_paid']       ?? 500),
        (string)$progDue,
        (string)($data['final_payment_due']  ?? ''),
        ($data['final_payment_enabled'] ?? false) ? '1' : '0',
        (string)($data['amount_paid_total']  ?? 500),
        $data['created_at']           ?? date('Y-m-d H:i:s'),
        date('Y-m-d H:i:s'),
        $data['versions_json']        ?? '[]',
        $data['approved_version_id']  ?? '',
        $data['tracking_number']      ?? '',
        $data['skip_resin_requested']       ?? '',
        $data['ring_approved_notification'] ?? '',
        $data['facetime_requested']         ?? '',
        $data['care_plan_purchased']        ?? '',
        $data['care_plan_amount']           ?? '',
        $data['charge_tax'] ?? '1',
        $data['estimate_json']              ?? '',
        (string)($data['shipping_charge']   ?? 0),
    ]];

    try {
        $body = new \Google_Service_Sheets_ValueRange(['values' => $values]);
        $svc->spreadsheets_values->update($sheetId, "orders!A{$row}:AC{$row}", $body, ['valueInputOption' => 'USER_ENTERED']);
        return true;
    } catch (\Exception $e) {
        error_log("Sheets updateOrder error: " . $e->getMessage());
        return false;
    }
}

function markPaymentReceived(string $orderId, float $amount, string $type): bool {
    $order = getOrderById($orderId);
    if (!$order) return false;

    $svc = getPortalSheetsService();
    if (!$svc) return false;
    $sheetId = $_ENV['PORTAL_SHEET_ID'];
    $row = $order['row'];

    try {
        $newTotal = $order['amount_paid_total'] + $amount;
        $body = new \Google_Service_Sheets_ValueRange(['values' => [[(string)$newTotal]]]);
        $svc->spreadsheets_values->update($sheetId, "orders!P{$row}", $body, ['valueInputOption' => 'USER_ENTERED']);

        if ($type === 'deposit') {
            $b = new \Google_Service_Sheets_ValueRange(['values' => [[(string)$amount]]]);
            $svc->spreadsheets_values->update($sheetId, "orders!L{$row}", $b, ['valueInputOption' => 'USER_ENTERED']);
        } elseif ($type === 'progress') {
            $b = new \Google_Service_Sheets_ValueRange(['values' => [['0']]]);
            $svc->spreadsheets_values->update($sheetId, "orders!M{$row}", $b, ['valueInputOption' => 'USER_ENTERED']);
        } elseif ($type === 'final') {
            $b = new \Google_Service_Sheets_ValueRange(['values' => [['0']]]);
            $svc->spreadsheets_values->update($sheetId, "orders!N{$row}", $b, ['valueInputOption' => 'USER_ENTERED']);
            // Clear shipping charge if it was included
            if ((float)$order['shipping_charge'] > 0) {
                $sb2 = new \Google_Service_Sheets_ValueRange(['values' => [['0']]]);
                $svc->spreadsheets_values->update($sheetId, "orders!AC{$row}", $sb2, ['valueInputOption' => 'USER_ENTERED']);
            }
            // Advance status to Complete and Ready for Delivery
            $sb = new \Google_Service_Sheets_ValueRange(['values' => [['Complete and Ready for Delivery']]]);
            $svc->spreadsheets_values->update($sheetId, "orders!G{$row}", $sb, ['valueInputOption' => 'USER_ENTERED']);
        } elseif ($type === 'care-plan') {
            // Don't add to amount_paid_total — separate product
            $undoBody = new \Google_Service_Sheets_ValueRange(['values' => [[(string)$order['amount_paid_total']]]]);
            $svc->spreadsheets_values->update($sheetId, "orders!P{$row}", $undoBody, ['valueInputOption' => 'USER_ENTERED']);
            $b = new \Google_Service_Sheets_ValueRange(['values' => [['1', (string)$amount]]]);
            $svc->spreadsheets_values->update($sheetId, "orders!Y{$row}:Z{$row}", $b, ['valueInputOption' => 'USER_ENTERED']);
            $sb = new \Google_Service_Sheets_ValueRange(['values' => [['Complete and Ready for Delivery']]]);
            $svc->spreadsheets_values->update($sheetId, "orders!G{$row}", $sb, ['valueInputOption' => 'USER_ENTERED']);
        } elseif ($type === 'final-with-care-plan') {
            // Clear final payment due
            $b = new \Google_Service_Sheets_ValueRange(['values' => [['0']]]);
            $svc->spreadsheets_values->update($sheetId, "orders!N{$row}", $b, ['valueInputOption' => 'USER_ENTERED']);
            // Clear shipping charge if it was included
            if ((float)$order['shipping_charge'] > 0) {
                $sb2 = new \Google_Service_Sheets_ValueRange(['values' => [['0']]]);
                $svc->spreadsheets_values->update($sheetId, "orders!AC{$row}", $sb2, ['valueInputOption' => 'USER_ENTERED']);
            }
            // Mark care plan purchased (store full combined amount in care_plan_amount for reference)
            $cp = new \Google_Service_Sheets_ValueRange(['values' => [['1', (string)$amount]]]);
            $svc->spreadsheets_values->update($sheetId, "orders!Y{$row}:Z{$row}", $cp, ['valueInputOption' => 'USER_ENTERED']);
            // Advance status
            $sb = new \Google_Service_Sheets_ValueRange(['values' => [['Complete and Ready for Delivery']]]);
            $svc->spreadsheets_values->update($sheetId, "orders!G{$row}", $sb, ['valueInputOption' => 'USER_ENTERED']);
        }
        return true;
    } catch (\Exception $e) {
        error_log("Sheets markPaymentReceived error: " . $e->getMessage());
        return false;
    }
}

// ── MEDIA sheet ───────────────────────────────────────────────────────────────

function getMediaForOrder(string $orderId): array {
    $svc = getPortalSheetsService();
    if (!$svc) return [];
    $sheetId = $_ENV['PORTAL_SHEET_ID'];

    try {
        $response = $svc->spreadsheets_values->get($sheetId, 'media!A2:H');
        $rows = $response->getValues() ?? [];
        $result = [];
        foreach ($rows as $row) {
            if (($row[1] ?? '') === $orderId) {
                $result[] = [
                    'media_id'      => $row[0] ?? '',
                    'order_id'      => $row[1] ?? '',
                    'uploader'      => $row[2] ?? '',
                    'filename'      => $row[3] ?? '',
                    'drive_file_id' => $row[4] ?? '',
                    'thumbnail_url' => $row[5] ?? '',
                    'uploaded_at'   => $row[6] ?? '',
                    'caption'       => $row[7] ?? '',
                ];
            }
        }
        return $result;
    } catch (\Exception $e) {
        error_log("Sheets getMediaForOrder error: " . $e->getMessage());
        return [];
    }
}

function addMediaRecord(array $data): bool {
    $svc = getPortalSheetsService();
    if (!$svc) return false;
    $sheetId = $_ENV['PORTAL_SHEET_ID'];

    $values = [[
        $data['media_id'],
        $data['order_id'],
        $data['uploader'],
        $data['filename'],
        $data['drive_file_id'] ?? '',
        $data['thumbnail_url'] ?? '',
        date('Y-m-d H:i:s'),
        $data['caption'] ?? '',
    ]];

    try {
        $body = new \Google_Service_Sheets_ValueRange(['values' => $values]);
        $svc->spreadsheets_values->append($sheetId, 'media!A:A', $body, [
            'valueInputOption'  => 'USER_ENTERED',
            'insertDataOption'  => 'INSERT_ROWS',
        ]);
        return true;
    } catch (\Exception $e) {
        error_log("Sheets addMediaRecord error: " . $e->getMessage());
        return false;
    }
}

function deleteMediaRecord(string $mediaId, string $orderId): array|false {
    $svc = getPortalSheetsService();
    if (!$svc) return false;
    $sheetId = $_ENV['PORTAL_SHEET_ID'];

    try {
        $response = $svc->spreadsheets_values->get($sheetId, 'media!A2:H');
        $rows = $response->getValues() ?? [];
        foreach ($rows as $i => $row) {
            if (($row[0] ?? '') === $mediaId && ($row[1] ?? '') === $orderId) {
                $rowNum = $i + 2;
                $thumbnailUrl = $row[5] ?? '';
                $body = new \Google_Service_Sheets_ValueRange(['values' => [['', '', '', '', '', '', '', '']]]);
                $svc->spreadsheets_values->update($sheetId, "media!A{$rowNum}:H{$rowNum}", $body,
                    ['valueInputOption' => 'RAW']);
                return ['thumbnail_url' => $thumbnailUrl];
            }
        }
        return false;
    } catch (\Exception $e) {
        error_log("Sheets deleteMediaRecord error: " . $e->getMessage());
        return false;
    }
}

function updateMediaCaption(string $mediaId, string $orderId, string $caption): bool {
    $svc = getPortalSheetsService();
    if (!$svc) return false;
    $sheetId = $_ENV['PORTAL_SHEET_ID'];

    try {
        $response = $svc->spreadsheets_values->get($sheetId, 'media!A2:H');
        $rows = $response->getValues() ?? [];
        foreach ($rows as $i => $row) {
            if (($row[0] ?? '') === $mediaId && ($row[1] ?? '') === $orderId) {
                $rowNum = $i + 2;
                $body = new \Google_Service_Sheets_ValueRange(['values' => [[$caption]]]);
                $svc->spreadsheets_values->update($sheetId, "media!H{$rowNum}", $body,
                    ['valueInputOption' => 'USER_ENTERED']);
                return true;
            }
        }
        return false;
    } catch (\Exception $e) {
        error_log("Sheets updateMediaCaption error: " . $e->getMessage());
        return false;
    }
}

// ── TOKENS sheet ──────────────────────────────────────────────────────────────
// NOTE: The Google Sheet must have a "tokens" tab with headers in row 1:
//   A=token | B=email | C=expires_at | D=used

function createMagicLinkToken(string $email): string {
    $svc = getPortalSheetsService();
    if (!$svc) return '';
    $sheetId = $_ENV['PORTAL_SHEET_ID'];

    $token   = bin2hex(random_bytes(32)); // 64-char hex
    $expires = date('Y-m-d H:i:s', strtotime('+48 hours'));

    try {
        $body = new \Google_Service_Sheets_ValueRange(['values' => [[$token, $email, $expires, '0']]]);
        $svc->spreadsheets_values->append($sheetId, 'tokens!A:D', $body, ['valueInputOption' => 'RAW']);
    } catch (\Exception $e) {
        error_log("createMagicLinkToken error: " . $e->getMessage());
    }
    return $token;
}

function getMagicLinkToken(string $token): ?array {
    $svc = getPortalSheetsService();
    if (!$svc) return null;
    $sheetId = $_ENV['PORTAL_SHEET_ID'];

    try {
        $response = $svc->spreadsheets_values->get($sheetId, 'tokens!A2:D');
        $rows = $response->getValues() ?? [];
        foreach ($rows as $i => $row) {
            if (trim($row[0] ?? '') === trim($token)) {
                return [
                    'row'        => $i + 2,
                    'token'      => $row[0] ?? '',
                    'email'      => $row[1] ?? '',
                    'expires_at' => $row[2] ?? '',
                    'used'       => $row[3] ?? '0',
                ];
            }
        }
    } catch (\Exception $e) {
        error_log("getMagicLinkToken error: " . $e->getMessage());
    }
    return null;
}

function markMagicLinkTokenUsed(int $row): void {
    $svc = getPortalSheetsService();
    if (!$svc) return;
    $sheetId = $_ENV['PORTAL_SHEET_ID'];

    try {
        $body = new \Google_Service_Sheets_ValueRange(['values' => [['1']]]);
        $svc->spreadsheets_values->update($sheetId, "tokens!D{$row}", $body, ['valueInputOption' => 'RAW']);
    } catch (\Exception $e) {
        error_log("markMagicLinkTokenUsed error: " . $e->getMessage());
    }
}
