<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/bootstrap.php';

portalSessionStart();
if (isLoggedInAsCustomer()) {
    header('Location: /portal.php');
    exit();
}

$magicEmail = $_SESSION['magic_link_email'] ?? '';
$showSetPassword = (!empty($magicEmail) || ($_GET['mode'] ?? '') === 'set_password') && !empty($magicEmail);
$linkError = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>My Ring Project — The Right Ring</title>
  <link rel="icon" type="image/png" href="https://framerusercontent.com/images/FHftFuIChaavuwoII685yqNf6A.png">
  <link rel="stylesheet" href="/assets/portal.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-box">
    <img class="logo" src="https://framerusercontent.com/images/FHftFuIChaavuwoII685yqNf6A.png" alt="The Right Ring">
    <h2>My Ring Project</h2>
    <p class="subtitle">Track your custom ring from design to delivery.</p>

    <?php if ($linkError === 'invalid_link'): ?>
    <div class="error-msg" style="display:block;margin-bottom:16px;">This link is not valid. Please contact us at <a href="mailto:design@therightring.com">design@therightring.com</a> and we will get you a new one.</div>
    <?php elseif ($linkError === 'link_used'): ?>
    <div class="error-msg" style="display:block;margin-bottom:16px;">This link has already been used. Log in with your password below, or use the <strong>First Time?</strong> tab to get a new link.</div>
    <?php elseif ($linkError === 'link_expired'): ?>
    <div style="background:#FEF3C7;border:1px solid #F59E0B;border-radius:8px;padding:14px 16px;margin-bottom:16px;">
      <div style="font-weight:700;color:#92400E;margin-bottom:6px;">Your login link has expired</div>
      <div style="font-size:13px;color:#78350F;margin-bottom:10px;">Links are only valid for 48 hours. Enter your email below and we will send you a fresh one right away.</div>
      <div id="resend-error" class="error-msg" style="display:none;margin-bottom:8px;"></div>
      <div id="resend-success" class="success-msg" style="display:none;margin-bottom:8px;"></div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <input type="email" id="resend-email" class="form-control" placeholder="your@email.com" style="flex:1;min-width:0;">
        <button type="button" id="btn-resend-link" class="btn btn-primary btn-sm" style="white-space:nowrap;">Send New Link</button>
      </div>
    </div>
    <?php endif; ?>

    <!-- Login section -->
    <div id="login-section" <?php if ($showSetPassword): ?>style="display:none"<?php endif; ?>>
      <div class="login-tabs">
        <button class="login-tab active" data-tab="initial">First Time?</button>
        <button class="login-tab" data-tab="password">I Have a Password</button>
      </div>

      <div id="msg-error" class="error-msg" style="display:none"></div>
      <div id="msg-success" class="success-msg" style="display:none"></div>

      <!-- Tab 1: Email + last 4 of phone -->
      <form id="form-initial" autocomplete="on">
        <div class="form-group">
          <label for="login-email">Email Address</label>
          <input type="email" id="login-email" class="form-control" required placeholder="you@example.com" autocomplete="email">
        </div>
        <div class="form-group">
          <label for="login-phone4">Last 4 Digits of Phone</label>
          <input type="text" id="login-phone4" class="form-control" required placeholder="1234"
                 maxlength="4" pattern="\d{4}" inputmode="numeric" autocomplete="tel">
          <div class="form-hint">Enter the last 4 digits of the phone number you used when you submitted your ring design (e.g. 5678).</div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">Continue</button>
      </form>

      <!-- Tab 2: Email + password -->
      <form id="form-password" style="display:none" autocomplete="on">
        <div class="form-group">
          <label for="pw-email">Email Address</label>
          <input type="email" id="pw-email" class="form-control" required placeholder="you@example.com" autocomplete="email">
        </div>
        <div class="form-group">
          <label for="pw-password">Password</label>
          <input type="password" id="pw-password" class="form-control" required placeholder="Your password" autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">Log In</button>
      </form>
    </div>

    <!-- Set password section (shown after magic link or first-time email+phone verification) -->
    <div id="set-password-section" <?php if (!$showSetPassword): ?>style="display:none"<?php endif; ?>>
      <p style="margin-bottom:20px;font-size:14px;color:var(--muted);">
        Welcome! Create a password to access your ring project portal. You will use this to log in any time.
      </p>
      <form id="form-set-password">
        <input type="hidden" id="sp-email" value="<?php echo htmlspecialchars($magicEmail); ?>">
        <input type="hidden" id="sp-token-mode" value="<?php echo $showSetPassword ? '1' : '0'; ?>">
        <input type="hidden" id="sp-phone4">
        <div class="form-group">
          <label for="sp-password">New Password</label>
          <input type="password" id="sp-password" class="form-control" required placeholder="At least 8 characters" minlength="8" autocomplete="new-password">
        </div>
        <div class="form-group">
          <label for="sp-confirm">Confirm Password</label>
          <input type="password" id="sp-confirm" class="form-control" required placeholder="Repeat password" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">Set Password & Continue</button>
      </form>
    </div>

  </div>
</div>

<script src="/assets/portal.js"></script>
<script>Login.init();</script>
</body>
</html>
