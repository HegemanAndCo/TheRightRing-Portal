<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sheets.php';
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']); exit;
}

$input    = json_decode(file_get_contents('php://input'), true);
$orderId  = trim($input['order_id'] ?? '');
$lines    = $input['lines'] ?? [];   // [{label, amount}]

if (empty($orderId)) {
    echo json_encode(['success' => false, 'error' => 'Order ID required.']); exit;
}
if (empty($lines) || !is_array($lines)) {
    echo json_encode(['success' => false, 'error' => 'At least one line item is required.']); exit;
}

$order = getOrderById($orderId);
if (!$order) {
    echo json_encode(['success' => false, 'error' => 'Order not found.']); exit;
}

// Compute total
$total = 0;
foreach ($lines as $line) {
    $total += (float)($line['amount'] ?? 0);
}

// Persist estimate_json + update total_estimate on the order
$order['estimate_json']  = json_encode($lines);
$order['total_estimate'] = $total;
$ok = updateOrder($order['row'], $order);
if (!$ok) {
    echo json_encode(['success' => false, 'error' => 'Failed to save estimate to Google Sheets.']); exit;
}

// Send the email
$sent = sendEstimateEmail(
    $order['email'],
    $order['customer_name'],
    $orderId,
    $lines,
    $total
);

if (!$sent) {
    echo json_encode(['success' => false, 'error' => 'Estimate saved but email failed to send. Check SMTP settings.']); exit;
}

echo json_encode(['success' => true, 'total' => $total]);

// ─────────────────────────────────────────────────────────────────────────────

function sendEstimateEmail(string $email, string $name, string $orderId, array $lines, float $total): bool {
    try {
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
        $mail->isHTML(true);
        $mail->Subject = 'Your Custom Ring Estimate from The Right Ring';
        $mail->CharSet = 'UTF-8';

        $token     = createMagicLinkToken($email);
        $magicLink = 'https://portal.therightring.com/magic.php?token=' . urlencode($token);
        $logoUrl   = 'https://framerusercontent.com/images/FHftFuIChaavuwoII685yqNf6A.png';
        $totalFmt  = '$' . number_format($total, 0);
        $firstName = explode(' ', trim($name))[0];

        // Build line item rows
        $lineRows = '';
        foreach ($lines as $line) {
            $label  = htmlspecialchars($line['label'] ?? '', ENT_QUOTES, 'UTF-8');
            $amount = (float)($line['amount'] ?? 0);
            $amtFmt = '$' . number_format($amount, 0);
            $lineRows .= "
              <tr>
                <td style='padding:11px 0;font-size:15px;color:#374151;border-bottom:1px solid #f0f2f5;'>{$label}</td>
                <td style='padding:11px 0;font-size:15px;color:#232429;font-weight:600;text-align:right;border-bottom:1px solid #f0f2f5;'>{$amtFmt}</td>
              </tr>";
        }

        $mail->Body = '<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f4f8;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:32px 16px;">
  <tr><td align="center">
  <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;">

    <!-- Header -->
    <tr>
      <td style="background:#2D5F8A;padding:28px 36px;">
        <img src="' . $logoUrl . '" alt="The Right Ring" width="160" height="auto" style="display:block;width:160px;height:auto;border:0;">
      </td>
    </tr>

    <!-- Body -->
    <tr>
      <td style="padding:36px 36px 0;">
        <h1 style="margin:0 0 20px;font-size:24px;font-weight:700;color:#1a2e3b;line-height:1.3;">Your Custom Ring Estimate</h1>
        <p style="margin:0 0 16px;font-size:15px;color:#4b5563;line-height:1.7;">Hi ' . $firstName . ',</p>
        ' . ((float)($order['deposit_paid'] ?? 0) > 0
          ? '<p style="margin:0 0 16px;font-size:15px;color:#4b5563;line-height:1.7;">Your deposit is in and we\'re already getting to work &mdash; I wanted to make sure you have your full price breakdown in one place for your records.</p>
        <p style="margin:0 0 28px;font-size:15px;color:#4b5563;line-height:1.7;">Below is your personalized price estimate based on your selections. The final price may vary slightly depending on the exact stone and metal details we finalize together.</p>'
          : '<p style="margin:0 0 16px;font-size:15px;color:#4b5563;line-height:1.7;">Thank you so much for designing your ring with us. It was a pleasure putting this together for you, and I am really excited about how this is going to turn out.</p>
        <p style="margin:0 0 28px;font-size:15px;color:#4b5563;line-height:1.7;">Below is your personalized price estimate based on your selections. Please keep in mind that the final price may vary slightly depending on the exact stone and metal details we finalize together.</p>'
        ) . '
      </td>
    </tr>

    <!-- Estimate table -->
    <tr>
      <td style="padding:0 36px;">
        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
          <tr>
            <td colspan="2" style="background:#f8fafc;padding:12px 20px;font-size:12px;font-weight:700;color:#6b7280;letter-spacing:0.08em;text-transform:uppercase;border-bottom:1px solid #e5e7eb;">Price Breakdown</td>
          </tr>
          <tr>
            <td colspan="2" style="padding:4px 20px 0;">
              <table width="100%" cellpadding="0" cellspacing="0">
                ' . $lineRows . '
                <tr>
                  <td style="padding:16px 0 12px;font-size:16px;font-weight:700;color:#1a2e3b;">Estimated Total</td>
                  <td style="padding:16px 0 12px;font-size:22px;font-weight:800;color:#2D5F8A;text-align:right;">' . $totalFmt . '</td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- 3D model section -->
    <tr>
      <td style="padding:28px 36px 0;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:10px;">
          <tr>
            <td style="padding:20px 24px;">
              <p style="margin:0 0 10px;font-size:15px;font-weight:700;color:#1E40AF;">Interactive 3D Ring Preview</p>
              <p style="margin:0 0 12px;font-size:14px;color:#374151;line-height:1.7;">One thing I want to mention &#8212; we would love to create an interactive 3D model of your ring before we build it. It truly makes a difference in seeing exactly how your ring will look, and most of our customers find it really helpful to be able to rotate and zoom in on every detail before giving final approval.</p>
              ' . ((float)($order['deposit_paid'] ?? 0) > 0
                ? '<p style="margin:0;font-size:14px;color:#374151;line-height:1.7;">Your deposit is in and we\'re getting started. We\'ll be in touch shortly to begin sourcing stones and working on your 3D model.</p>'
                : '<p style="margin:0 0 10px;font-size:14px;color:#374151;line-height:1.7;">Because it does take us time and craftsmanship to produce, a $250 design deposit is required to get started. Once that is in, we get to work right away.</p><p style="margin:0;font-size:14px;color:#374151;line-height:1.7;"><em>Your deposit simply starts the process &mdash; no design is locked in. We\'ll work with you through every revision until it\'s exactly right.</em></p>'
              ) . '
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- CTA -->
    <tr>
      <td style="padding:28px 36px 0;text-align:center;">
        <p style="margin:0 0 20px;font-size:15px;color:#4b5563;line-height:1.7;">Ready to move forward? Click below to log in to your personal project portal, set up your account, and pay your deposit to get things started.</p>
        <a href="' . $magicLink . '" style="display:inline-block;background:#2D5F8A;color:#ffffff;padding:16px 40px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;letter-spacing:0.01em;">View My Estimate &amp; Get Started</a>
        <p style="margin:16px 0 0;font-size:12px;color:#9ca3af;">This link expires in 48 hours. Once logged in you can review your estimate, set your password, and pay your deposit securely.</p>
      </td>
    </tr>

    <!-- Sign-off -->
    <tr>
      <td style="padding:32px 36px 36px;">
        <p style="margin:0 0 4px;font-size:15px;color:#4b5563;line-height:1.7;">Looking forward to creating something truly special for you,</p>
        <p style="margin:0 0 4px;font-size:15px;font-weight:700;color:#1a2e3b;">Matt</p>
        <p style="margin:0;font-size:14px;color:#6b7280;">The Right Ring &nbsp;|&nbsp; <a href="mailto:design@therightring.com" style="color:#2D5F8A;text-decoration:none;">design@therightring.com</a></p>
      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td style="background:#f8fafc;border-top:1px solid #e5e7eb;padding:16px 36px;text-align:center;">
        <p style="margin:0;font-size:12px;color:#9ca3af;">You received this email because you requested a custom ring estimate from The Right Ring. If you did not expect this, you can safely ignore it.</p>
      </td>
    </tr>

  </table>
  </td></tr>
</table>
</body>
</html>';

        $lineText = '';
        foreach ($lines as $line) {
            $lineText .= '  ' . ($line['label'] ?? '') . ': $' . number_format((float)($line['amount'] ?? 0), 0) . "\n";
        }
        $depositPaid = (float)($order['deposit_paid'] ?? 0) > 0;
        $depositNote = $depositPaid
            ? "Your deposit is in and we're getting started. We'll be in touch shortly to begin sourcing stones and working on your 3D model."
            : "We would love to create an interactive 3D model of your ring before we build it. A \$250 design deposit is required to get started. Your deposit simply starts the process — no design is locked in. We'll work with you through every revision until it's exactly right.";
        $mail->AltBody = "Hi {$firstName},\n\nThank you for designing your ring with us. Here is your price estimate:\n\n{$lineText}\nEstimated Total: {$totalFmt}\n\n{$depositNote}\n\nLog in to your portal to view your estimate:\n{$magicLink}\n\n(This link expires in 48 hours.)\n\nLooking forward to creating something special for you,\nMatt\nThe Right Ring\ndesign@therightring.com";

        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log("sendEstimateEmail failed: " . $e->getMessage());
        return false;
    }
}
