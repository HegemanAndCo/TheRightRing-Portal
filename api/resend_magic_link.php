<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sheets.php';
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = strtolower(trim($input['email'] ?? ''));

if (empty($email)) {
    echo json_encode(['success' => false, 'error' => 'Email is required.']); exit;
}

$user = getUserByEmail($email);
if (!$user) {
    // Don't reveal whether the email exists
    echo json_encode(['success' => true]); exit;
}

$token     = createMagicLinkToken($email);
$magicLink = 'https://portal.therightring.com/magic.php?token=' . urlencode($token);
$logoUrl   = 'https://framerusercontent.com/images/FHftFuIChaavuwoII685yqNf6A.png';
$name      = $user['full_name'] ?? $email;
$firstName = explode(' ', trim($name))[0];

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
    $mail->CharSet  = 'UTF-8';
    $mail->isHTML(true);
    $mail->Subject  = 'Your New Login Link \u2014 The Right Ring';
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
    <h1 style="margin:0 0 20px;font-size:24px;font-weight:700;color:#1a2e3b;">Here is your new login link</h1>
    <p style="margin:0 0 16px;font-size:15px;color:#4b5563;line-height:1.7;">Hi ' . $firstName . ',</p>
    <p style="margin:0 0 28px;font-size:15px;color:#4b5563;line-height:1.7;">No problem &#8212; here is a fresh link to access your ring project portal. Click the button below to log in and set your password.</p>
  </td></tr>
  <tr><td style="padding:0 36px 28px;text-align:center;">
    <a href="' . $magicLink . '" style="display:inline-block;background:#2D5F8A;color:#ffffff;padding:16px 40px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;">Log In to My Project</a>
    <p style="margin:14px 0 0;font-size:12px;color:#9ca3af;">This link expires in 48 hours.</p>
  </td></tr>
  <tr><td style="padding:0 36px 36px;">
    <p style="margin:0 0 4px;font-size:15px;color:#4b5563;">Talk soon,</p>
    <p style="margin:0 0 4px;font-size:15px;font-weight:700;color:#1a2e3b;">Matt</p>
    <p style="margin:0;font-size:14px;color:#6b7280;">The Right Ring &nbsp;|&nbsp; <a href="mailto:design@therightring.com" style="color:#2D5F8A;text-decoration:none;">design@therightring.com</a></p>
  </td></tr>
  <tr><td style="background:#f8fafc;border-top:1px solid #e5e7eb;padding:16px 36px;text-align:center;">
    <p style="margin:0;font-size:12px;color:#9ca3af;">If you did not request this, you can safely ignore it.</p>
  </td></tr>
</table>
</td></tr></table>
</body></html>';
    $mail->send();
} catch (\Exception $e) {
    error_log("resend_magic_link email failed: " . $e->getMessage());
}

echo json_encode(['success' => true]);
