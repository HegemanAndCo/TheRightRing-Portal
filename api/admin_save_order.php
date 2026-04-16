<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sheets.php';

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');
requireAdminLogin();

$fullAdmin = isFullAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$mode  = $input['mode'] ?? 'update'; // 'create' | 'update'

if ($mode === 'create') {
    if (!$fullAdmin) {
        jsonResponse(['success' => false, 'error' => 'Not authorized.'], 403);
    }
    // Generate a new order ID
    $orderId = 'TRR-' . strtoupper(substr(uniqid(), -6));

    $data = [
        'order_id'       => $orderId,
        'customer_name'  => trim($input['customer_name'] ?? ''),
        'email'          => strtolower(trim($input['email'] ?? '')),
        'phone'          => trim($input['phone'] ?? ''),
        'address'        => trim($input['address'] ?? ''),
        'ring_choices_json' => $input['ring_choices_json'] ?? '[]',
        'total_estimate' => (float)($input['total_estimate'] ?? 0),
    ];

    if (empty($data['customer_name']) || empty($data['email'])) {
        jsonResponse(['success' => false, 'error' => 'Customer name and email are required.']);
    }

    // Create user record
    $phone4 = substr(preg_replace('/\D/', '', $data['phone']), -4);

    require_once __DIR__ . '/../lib/sheets.php';
    createOrder($data);
    createUser([
        'email'        => $data['email'],
        'phone_last4'  => $phone4,
        'full_name'    => $data['customer_name'],
        'order_id'     => $orderId,
        'password_hash'=> '',
    ]);

    // Send portal invite email
    sendPortalInviteEmail($data['email'], $data['customer_name'], $phone4, $orderId);

    jsonResponse(['success' => true, 'order_id' => $orderId]);

} elseif ($mode === 'update') {
    $orderId = trim($input['order_id'] ?? '');
    if (empty($orderId)) {
        jsonResponse(['success' => false, 'error' => 'Order ID required for update.']);
    }

    $existing = getOrderById($orderId);
    if (!$existing) {
        jsonResponse(['success' => false, 'error' => 'Order not found.']);
    }

    if ($fullAdmin) {
        $data = array_merge($existing, [
            'order_id'              => $orderId,
            'customer_name'         => trim($input['customer_name']         ?? $existing['customer_name']),
            'email'                 => strtolower(trim($input['email']       ?? $existing['email'])),
            'phone'                 => trim($input['phone']                 ?? $existing['phone']),
            'address'               => trim($input['address']               ?? $existing['address']),
            'ring_choices_json'     => $input['ring_choices_json']          ?? $existing['ring_choices_json'],
            'status'                => $input['status']                     ?? $existing['status'],
            'timeline_note'         => $input['timeline_note']              ?? $existing['timeline_note'],
            'estimated_completion'  => $input['estimated_completion']       ?? $existing['estimated_completion'],
            'project_update'        => $input['project_update']             ?? $existing['project_update'],
            'total_estimate'        => (float)($input['total_estimate']     ?? $existing['total_estimate']),
            'deposit_paid'          => (float)($input['deposit_paid']       ?? $existing['deposit_paid']),
            'final_payment_due'     => (float)($input['final_payment_due']  ?? $existing['final_payment_due']),
            'final_payment_enabled' => (bool)($input['final_payment_enabled'] ?? $existing['final_payment_enabled']),
            'amount_paid_total'     => (float)($input['amount_paid_total']  ?? $existing['amount_paid_total']),
            'versions_json'         => $input['versions_json']              ?? $existing['versions_json'],
            'approved_version_id'        => $input['approved_version_id']             ?? $existing['approved_version_id'],
            'ring_approved_notification' => $input['ring_approved_notification']    ?? $existing['ring_approved_notification'] ?? '',
            'tracking_number'       => trim($input['tracking_number']       ?? $existing['tracking_number'] ?? ''),
            'skip_resin_requested'  => $input['skip_resin_requested']      ?? $existing['skip_resin_requested'] ?? '',
            'estimate_json'         => $input['estimate_json']             ?? $existing['estimate_json'] ?? '',
            'shipping_charge'       => (float)($input['shipping_charge']   ?? $existing['shipping_charge'] ?? 0),
            'created_at'            => $existing['created_at'],
        ]);
    } else {
        // Limited admin (Kiana): only status, timeline, and tracking
        $data = array_merge($existing, [
            'status'               => $input['status']               ?? $existing['status'],
            'timeline_note'        => $input['timeline_note']        ?? $existing['timeline_note'],
            'estimated_completion' => $input['estimated_completion'] ?? $existing['estimated_completion'],
            'project_update'       => $input['project_update']       ?? $existing['project_update'],
            'tracking_number'      => trim($input['tracking_number'] ?? $existing['tracking_number'] ?? ''),
        ]);
    }

    // Handle progress deposit override
    if (isset($input['progress_deposit_due'])) {
        $data['progress_deposit_due'] = (float)$input['progress_deposit_due'];
    }

    $ok = updateOrder($existing['row'], $data);
    if (!$ok) {
        jsonResponse(['success' => false, 'error' => 'Failed to save to Google Sheets. Please try again.']);
    }

    // Email customer if status changed
    $oldStatus     = $existing['status'] ?? '';
    $newStatus     = $data['status'] ?? '';
    $suppressEmail = !empty($input['suppress_email']) || !$fullAdmin;
    if ($newStatus && $newStatus !== $oldStatus && !$suppressEmail) {
        sendStatusChangeEmail($data['email'], $data['customer_name'], $newStatus, $orderId, $data);
    }

    // Email customer if final payment was just enabled (only if status didn't already trigger the ring-finished email)
    $wasEnabled = !empty($existing['final_payment_enabled']);
    $nowEnabled = !empty($data['final_payment_enabled']);
    $ringFinishedEmailAlreadySent = ($newStatus === 'Complete and Awaiting Payment' && $newStatus !== $oldStatus);
    if ($nowEnabled && !$wasEnabled && !$ringFinishedEmailAlreadySent && !$suppressEmail) {
        sendFinalPaymentEmail($data['email'], $data['customer_name'], (float)$data['final_payment_due'], $orderId);
    }

    jsonResponse(['success' => true]);

} else {
    jsonResponse(['success' => false, 'error' => 'Invalid mode.']);
}

function mailerBase(): \PHPMailer\PHPMailer\PHPMailer {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['SMTP_USERNAME'];
    $mail->Password   = $_ENV['SMTP_PASSWORD'];
    $mail->SMTPSecure = 'tls';
    $mail->Port       = (int)$_ENV['SMTP_PORT'];
    $mail->setFrom($_ENV['SMTP_FROM_EMAIL'], $_ENV['SMTP_FROM_NAME']);
    $mail->isHTML(true);
    return $mail;
}

function sendStatusChangeEmail(string $email, string $name, string $newStatus, string $orderId, array $data = []): void {
    // Internal statuses that don't warrant a customer email
    $silent = ['Design Review', 'Design in Process', 'Casting'];
    if (in_array($newStatus, $silent)) return;

    $portalUrl  = 'https://portal.therightring.com';
    $logoUrl    = 'https://framerusercontent.com/images/FHftFuIChaavuwoII685yqNf6A.png';
    $firstName  = explode(' ', trim($name))[0];

    // Map each internal status to a friendly customer-facing label + message
    $statusMap = [
        'Awaiting Design Approval' => [
            'subject'  => 'Your Ring Design Is Ready to Review',
            'headline' => 'Your ring design is ready for your review',
            'body'     => '__DESIGN_APPROVAL_BODY__',
            'cta'      => 'Review My Design in the Portal',
        ],
        '3D Printing Resin/Wax Model' => [
            'subject'  => 'Your 3D Model Is Being Printed',
            'headline' => 'Your 3D model is on its way',
            'body'     => "Great news &#8212; we have started printing your 3D resin model. This gives you a tangible, true-to-scale preview of your ring before we cast it in metal. We will be in touch as soon as it is ready for your approval.",
            'cta'      => 'View My Project',
        ],
        'Awaiting 3D Printed Resin Approval' => [
            'subject'  => 'Your 3D Resin Model Is Ready to Approve',
            'headline' => 'Your 3D resin model is ready',
            'body'     => "Your 3D printed resin model is finished and we are excited for you to see it. Please log in to your portal to review it and give your approval so we can move into production.",
            'cta'      => 'Review My 3D Model',
        ],
        'In Production' => [
            'subject'  => 'Your Ring Is Now In Production',
            'headline' => 'Your ring is officially in production',
            'body'     => "This is the moment we have all been working toward &#8212; your ring is now being crafted. Our jewelers are bringing your design to life and we will keep you updated as it progresses.",
            'cta'      => 'View My Project',
        ],
        'Setting Stones' => [
            'subject'  => 'Your Stones Are Being Set',
            'headline' => 'Your stones are being set',
            'body'     => "We are in the final stages of production &#8212; your stones are now being carefully set into the ring. We are almost there and cannot wait for you to see the finished piece.",
            'cta'      => 'View My Project',
        ],
        'Complete and Ready for Delivery' => [
            'subject'  => 'Your Ring Is On Its Way',
            'headline' => 'Your ring is on its way to you',
            'body'     => "Your ring is complete and has been prepared for delivery. Keep an eye on your portal for tracking information. We are so proud of how it turned out and we hope you love it just as much as we do.",
            'cta'      => 'Track My Order',
        ],
        'Sent Overnight Mail' => [
            'subject'  => 'Your Ring Has Been Shipped',
            'headline' => 'Your ring has been shipped',
            'body'     => "Your ring is on its way! We shipped it via overnight mail and your tracking number is available in your project portal. It should be with you very soon.",
            'cta'      => 'View Tracking Info',
        ],
    ];

    try {
        $mail = mailerBase();
        $mail->CharSet = 'UTF-8';
        $mail->addAddress($email, $name);

        // Special email for ring completion with balance due
        if ($newStatus === 'Complete and Awaiting Payment') {
            $total    = (float)($data['total_estimate']    ?? 0);
            $paid     = (float)($data['amount_paid_total'] ?? 0);
            $balance  = max(0, $total - $paid);
            $balFmt   = '$' . number_format($balance, 0);
            $totalFmt = '$' . number_format($total,   0);
            $paidFmt  = '$' . number_format($paid,    0);

            $mail->Subject = 'Great News &#8212; Your Ring Is Finished';
            $mail->Body = '<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f4f8;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;">
  <tr><td style="background:#2D5F8A;padding:28px 36px;">
    <img src="' . $logoUrl . '" alt="The Right Ring" width="160" height="auto" style="display:block;width:160px;height:auto;border:0;">
  </td></tr>
  <tr><td style="padding:36px 36px 0;">
    <h1 style="margin:0 0 20px;font-size:24px;font-weight:700;color:#1a2e3b;">Your ring is finished!</h1>
    <p style="margin:0 0 16px;font-size:15px;color:#4b5563;line-height:1.7;">Hi ' . $firstName . ',</p>
    <p style="margin:0 0 28px;font-size:15px;color:#4b5563;line-height:1.7;">We are thrilled to let you know that your custom ring is complete and looking absolutely beautiful. To get it shipped to you, your final balance is now due.</p>
  </td></tr>
  <tr><td style="padding:0 36px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
      <tr><td colspan="2" style="background:#f8fafc;padding:12px 20px;font-size:12px;font-weight:700;color:#6b7280;letter-spacing:0.08em;text-transform:uppercase;border-bottom:1px solid #e5e7eb;">Payment Summary</td></tr>
      <tr><td colspan="2" style="padding:4px 20px 0;">
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td style="padding:10px 0;font-size:14px;color:#6b7280;border-bottom:1px solid #f0f2f5;">Total Estimate</td>
            <td style="padding:10px 0;font-size:14px;color:#232429;font-weight:600;text-align:right;border-bottom:1px solid #f0f2f5;">' . $totalFmt . '</td>
          </tr>
          <tr>
            <td style="padding:10px 0;font-size:14px;color:#6b7280;border-bottom:1px solid #f0f2f5;">Amount Paid</td>
            <td style="padding:10px 0;font-size:14px;color:#059669;font-weight:600;text-align:right;border-bottom:1px solid #f0f2f5;">&#8722; ' . $paidFmt . '</td>
          </tr>
          <tr>
            <td style="padding:16px 0 12px;font-size:16px;font-weight:700;color:#1a2e3b;">Final Balance Due</td>
            <td style="padding:16px 0 12px;font-size:22px;font-weight:800;color:#2D5F8A;text-align:right;">' . $balFmt . '</td>
          </tr>
        </table>
      </td></tr>
    </table>
  </td></tr>
  <tr><td style="padding:28px 36px 0;text-align:center;">
    <a href="' . $portalUrl . '" style="display:inline-block;background:#2D5F8A;color:#ffffff;padding:16px 40px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;">Pay Final Balance</a>
  </td></tr>
  <tr><td style="padding:32px 36px 36px;">
    <p style="margin:0 0 4px;font-size:15px;color:#4b5563;line-height:1.7;">We are so proud of how it came out and cannot wait for you to see it,</p>
    <p style="margin:0 0 4px;font-size:15px;font-weight:700;color:#1a2e3b;">Matt</p>
    <p style="margin:0;font-size:14px;color:#6b7280;">The Right Ring &nbsp;|&nbsp; <a href="mailto:design@therightring.com" style="color:#2D5F8A;text-decoration:none;">design@therightring.com</a></p>
  </td></tr>
  <tr><td style="background:#f8fafc;border-top:1px solid #e5e7eb;padding:16px 36px;text-align:center;">
    <p style="margin:0;font-size:12px;color:#9ca3af;">The Right Ring &nbsp;|&nbsp; design@therightring.com</p>
  </td></tr>
</table>
</td></tr></table>
</body></html>';

        } elseif (isset($statusMap[$newStatus])) {
            $s        = $statusMap[$newStatus];
            $subject  = $s['subject'];
            $headline = $s['headline'];
            $bodyText = $s['body'];
            $cta      = $s['cta'];

            $mail->Subject = $subject;

            // Design Approval email gets a richer layout with render portal info
            if ($newStatus === 'Awaiting Design Approval') {
                $renderPortalUrl = 'https://render.therightring.com';
                $bodyContent = '
  <tr><td style="padding:36px 36px 0;">
    <h1 style="margin:0 0 20px;font-size:24px;font-weight:700;color:#1a2e3b;">' . $headline . '</h1>
    <p style="margin:0 0 16px;font-size:15px;color:#4b5563;line-height:1.7;">Hi ' . $firstName . ',</p>
    <p style="margin:0 0 16px;font-size:15px;color:#4b5563;line-height:1.7;">We have finished putting your custom ring design together and we are really excited about how it is coming along. I would love for you to take a look and share your thoughts.</p>
    <p style="margin:0 0 16px;font-size:15px;color:#4b5563;line-height:1.7;">You have <strong>two ways to review your design</strong>:</p>
  </td></tr>
  <tr><td style="padding:0 36px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;margin-bottom:24px;">
      <tr>
        <td style="padding:20px 24px;border-bottom:1px solid #f0f2f5;">
          <div style="font-weight:700;font-size:15px;color:#1a2e3b;margin-bottom:6px;">&#127775; Interactive 3D Model</div>
          <div style="font-size:14px;color:#4b5563;line-height:1.7;margin-bottom:10px;">Spin, zoom, and explore your ring from every angle in our interactive render portal. Use your <strong>full name as the password</strong> &#8212; exactly as it appears in your portal account (<strong>' . htmlspecialchars($name) . '</strong>).</div>
          <a href="' . $renderPortalUrl . '" style="display:inline-block;background:#1a2e3b;color:#ffffff;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:700;font-size:14px;">View 3D Model &#8594;</a>
        </td>
      </tr>
      <tr>
        <td style="padding:20px 24px;">
          <div style="font-weight:700;font-size:15px;color:#1a2e3b;margin-bottom:6px;">&#128203; Your Project Portal</div>
          <div style="font-size:14px;color:#4b5563;line-height:1.7;margin-bottom:10px;">Log in to your project portal to see all your design details and approve your design with one click &#8212; or leave a note if you would like any changes made.</div>
          <a href="' . $portalUrl . '" style="display:inline-block;background:#2D5F8A;color:#ffffff;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:700;font-size:14px;">' . $cta . ' &#8594;</a>
        </td>
      </tr>
    </table>
  </td></tr>
  <tr><td style="padding:0 36px 36px;">
    <p style="margin:0 0 16px;font-size:15px;color:#4b5563;line-height:1.7;">You can also just reply directly to this email if that is easier &#8212; whatever works best for you. If anything needs tweaking, just let me know and we will get it sorted right away.</p>
    <p style="margin:0 0 4px;font-size:15px;color:#4b5563;line-height:1.7;">Talk soon,</p>
    <p style="margin:0 0 4px;font-size:15px;font-weight:700;color:#1a2e3b;">Matt</p>
    <p style="margin:0;font-size:14px;color:#6b7280;">The Right Ring &nbsp;|&nbsp; <a href="mailto:design@therightring.com" style="color:#2D5F8A;text-decoration:none;">design@therightring.com</a></p>
  </td></tr>';
            } else {
                $bodyContent = '
  <tr><td style="padding:36px 36px 0;">
    <h1 style="margin:0 0 20px;font-size:24px;font-weight:700;color:#1a2e3b;">' . $headline . '</h1>
    <p style="margin:0 0 16px;font-size:15px;color:#4b5563;line-height:1.7;">Hi ' . $firstName . ',</p>
    <p style="margin:0 0 28px;font-size:15px;color:#4b5563;line-height:1.7;">' . $bodyText . '</p>
  </td></tr>
  <tr><td style="padding:0 36px 28px;text-align:center;">
    <a href="' . $portalUrl . '" style="display:inline-block;background:#2D5F8A;color:#ffffff;padding:16px 40px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;">' . $cta . '</a>
  </td></tr>
  <tr><td style="padding:0 36px 36px;">
    <p style="margin:0 0 4px;font-size:15px;color:#4b5563;line-height:1.7;">Talk soon,</p>
    <p style="margin:0 0 4px;font-size:15px;font-weight:700;color:#1a2e3b;">Matt</p>
    <p style="margin:0;font-size:14px;color:#6b7280;">The Right Ring &nbsp;|&nbsp; <a href="mailto:design@therightring.com" style="color:#2D5F8A;text-decoration:none;">design@therightring.com</a></p>
  </td></tr>';
            }

            $mail->Body = '<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f4f8;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;">
  <tr><td style="background:#2D5F8A;padding:28px 36px;">
    <img src="' . $logoUrl . '" alt="The Right Ring" width="160" height="auto" style="display:block;width:160px;height:auto;border:0;">
  </td></tr>
  ' . $bodyContent . '
  <tr><td style="background:#f8fafc;border-top:1px solid #e5e7eb;padding:16px 36px;text-align:center;">
    <p style="margin:0;font-size:12px;color:#9ca3af;">The Right Ring &nbsp;|&nbsp; design@therightring.com</p>
  </td></tr>
</table>
</td></tr></table>
</body></html>';

        } else {
            // Fallback for any unmapped status — should not normally be reached
            return;
        }

        $mail->send();
    } catch (\Exception $e) {
        error_log("Status change email failed: " . $e->getMessage());
    }
}

function sendFinalPaymentEmail(string $email, string $name, float $amount, string $orderId): void {
    try {
        $mail      = mailerBase();
        $mail->CharSet = 'UTF-8';
        $mail->addAddress($email, $name);
        $mail->Subject = 'Your Ring Is Complete — Final Balance Due';
        $portalUrl = 'https://portal.therightring.com';
        $logoUrl   = 'https://framerusercontent.com/images/FHftFuIChaavuwoII685yqNf6A.png';
        $fmt       = '$' . number_format($amount, 0);
        $firstName = explode(' ', trim($name))[0];
        $mail->Body = '<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f4f8;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;">
  <tr><td style="background:#2D5F8A;padding:28px 36px;">
    <img src="' . $logoUrl . '" alt="The Right Ring" width="160" height="auto" style="display:block;width:160px;height:auto;border:0;">
  </td></tr>
  <tr><td style="padding:36px 36px 0;">
    <h1 style="margin:0 0 20px;font-size:24px;font-weight:700;color:#1a2e3b;">Your ring is complete!</h1>
    <p style="margin:0 0 16px;font-size:15px;color:#4b5563;line-height:1.7;">Hi ' . $firstName . ',</p>
    <p style="margin:0 0 16px;font-size:15px;color:#4b5563;line-height:1.7;">We are so excited to share that your custom ring is finished and it looks absolutely stunning. We have posted final photos in your project portal &#8212; log in to take a look. If they are not there already, we will be adding them shortly.</p>
    <p style="margin:0 0 28px;font-size:15px;color:#4b5563;line-height:1.7;">Your final balance of <strong>' . $fmt . '</strong> is now due. Once payment is received we will ship your ring out right away.</p>
  </td></tr>
  <tr><td style="padding:0 36px 28px;text-align:center;">
    <a href="' . $portalUrl . '" style="display:inline-block;background:#2D5F8A;color:#ffffff;padding:16px 40px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;">View Photos &amp; Pay Final Balance</a>
  </td></tr>
  <tr><td style="padding:0 36px 36px;">
    <p style="margin:0 0 4px;font-size:15px;color:#4b5563;line-height:1.7;">We cannot wait for you to have it,</p>
    <p style="margin:0 0 4px;font-size:15px;font-weight:700;color:#1a2e3b;">Matt</p>
    <p style="margin:0;font-size:14px;color:#6b7280;">The Right Ring &nbsp;|&nbsp; <a href="mailto:design@therightring.com" style="color:#2D5F8A;text-decoration:none;">design@therightring.com</a></p>
  </td></tr>
  <tr><td style="background:#f8fafc;border-top:1px solid #e5e7eb;padding:16px 36px;text-align:center;">
    <p style="margin:0;font-size:12px;color:#9ca3af;">The Right Ring &nbsp;|&nbsp; design@therightring.com</p>
  </td></tr>
</table>
</td></tr></table>
</body></html>';
        $mail->send();
    } catch (\Exception $e) {
        error_log("Final payment email failed: " . $e->getMessage());
    }
}

function sendPortalInviteEmail(string $email, string $name, string $phone4, string $orderId): void {
    try {
        require_once __DIR__ . '/../../TheRightRing/vendor/autoload.php';
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USERNAME'];
        $mail->Password   = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = 'tls';
        $mail->Port       = (int)$_ENV['SMTP_PORT'];
        $mail->setFrom($_ENV['SMTP_FROM_EMAIL'], $_ENV['SMTP_FROM_NAME']);
        $mail->addAddress($email, $name);
        $mail->Subject  = 'Your Ring Project Portal Is Ready';
        $mail->CharSet  = 'UTF-8';
        $mail->isHTML(true);

        $token     = createMagicLinkToken($email);
        $magicLink = 'https://portal.therightring.com/magic.php?token=' . urlencode($token);
        $logoUrl   = 'https://framerusercontent.com/images/FHftFuIChaavuwoII685yqNf6A.png';
        $firstName = explode(' ', trim($name))[0];

        $mail->Body = '<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f4f8;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;">
  <tr><td style="background:#2D5F8A;padding:28px 36px;">
    <img src="' . $logoUrl . '" alt="The Right Ring" width="160" height="auto" style="display:block;width:160px;height:auto;border:0;">
  </td></tr>
  <tr><td style="padding:36px 36px 0;">
    <h1 style="margin:0 0 20px;font-size:24px;font-weight:700;color:#1a2e3b;">Your project portal is ready</h1>
    <p style="margin:0 0 16px;font-size:15px;color:#4b5563;line-height:1.7;">Hi ' . $firstName . ',</p>
    <p style="margin:0 0 28px;font-size:15px;color:#4b5563;line-height:1.7;">We have set up your personal ring project portal where you can track every step of your ring from design to delivery, review 3D models, and manage your payments. Click below to set up your password and get started.</p>
  </td></tr>
  <tr><td style="padding:0 36px 28px;text-align:center;">
    <a href="' . $magicLink . '" style="display:inline-block;background:#2D5F8A;color:#ffffff;padding:16px 40px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;">Set Up My Account</a>
    <p style="margin:14px 0 0;font-size:12px;color:#9ca3af;">This link expires in 48 hours. If you did not expect this email, you can safely ignore it.</p>
  </td></tr>
  <tr><td style="padding:0 36px 36px;">
    <p style="margin:0 0 4px;font-size:15px;color:#4b5563;line-height:1.7;">Looking forward to working with you,</p>
    <p style="margin:0 0 4px;font-size:15px;font-weight:700;color:#1a2e3b;">Matt</p>
    <p style="margin:0;font-size:14px;color:#6b7280;">The Right Ring &nbsp;|&nbsp; <a href="mailto:design@therightring.com" style="color:#2D5F8A;text-decoration:none;">design@therightring.com</a></p>
  </td></tr>
  <tr><td style="background:#f8fafc;border-top:1px solid #e5e7eb;padding:16px 36px;text-align:center;">
    <p style="margin:0;font-size:12px;color:#9ca3af;">The Right Ring &nbsp;|&nbsp; design@therightring.com</p>
  </td></tr>
</table>
</td></tr></table>
</body></html>';

        $mail->send();
    } catch (\Exception $e) {
        error_log("Portal invite email failed: " . $e->getMessage());
    }
}
