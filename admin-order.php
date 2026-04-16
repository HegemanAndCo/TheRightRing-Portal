<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/bootstrap.php';

requireAdminLogin();

$isNew      = isset($_GET['new']);
$orderId    = $_GET['id'] ?? '';
$title      = $isNew ? 'New Order' : 'Edit Order';
$isFullAdmin = isFullAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title) ?> — The Right Ring Portal</title>
  <link rel="icon" type="image/png" href="https://framerusercontent.com/images/FHftFuIChaavuwoII685yqNf6A.png">
  <link rel="stylesheet" href="/assets/portal.css">
</head>
<body>

<header class="admin-header">
  <div style="display:flex;align-items:center;gap:12px;">
    <img class="logo" src="https://framerusercontent.com/images/FHftFuIChaavuwoII685yqNf6A.png" alt="The Right Ring">
    <h1 id="page-title"><?= htmlspecialchars($title) ?></h1>
    <span style="font-size:12px;opacity:0.6;font-weight:500;"><?= $isFullAdmin ? "Matt's Admin Portal" : "Kiana's Admin Portal" ?></span>
  </div>
  <div style="display:flex;gap:10px;align-items:center;">
    <a href="/admin-dashboard.php" class="btn btn-outline btn-sm" style="color:#fff;border-color:rgba(255,255,255,0.3);">← All Orders</a>
    <a href="/logout.php" class="btn btn-outline btn-sm" style="color:#fff;border-color:rgba(255,255,255,0.3);">Log Out</a>
  </div>
</header>

<main class="admin-main">

  <!-- Ring approval notification banner (shown/hidden by JS) -->
  <div id="ring-approved-banner" style="display:none;background:#D1FAE5;border:1px solid #34D399;border-radius:8px;padding:14px 18px;margin-bottom:16px;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div style="display:flex;align-items:center;gap:10px;">
      <span style="font-size:20px;">✓</span>
      <div>
        <strong style="color:#065F46;">Customer approved a ring design version</strong>
        <div id="ring-approved-banner-label" style="font-size:13px;color:#047857;margin-top:2px;"></div>
      </div>
    </div>
    <button type="button" id="ring-approved-dismiss" class="btn btn-sm" style="background:#34D399;color:#fff;border:none;white-space:nowrap;">Acknowledge &amp; Dismiss</button>
  </div>

  <!-- Skip resin notification banner (shown/hidden by JS) -->
  <div id="skip-resin-banner" style="display:none;background:#FEF3C7;border:1px solid #F59E0B;border-radius:8px;padding:14px 18px;margin-bottom:16px;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div style="display:flex;align-items:center;gap:10px;">
      <span style="font-size:20px;">⚠️</span>
      <div>
        <strong style="color:#92400E;">Customer requested to skip 3D Resin printing</strong>
        <div style="font-size:13px;color:#78350F;margin-top:2px;">The customer would like to skip the 3D Printing Resin/Wax Model step and move directly to In Production.</div>
      </div>
    </div>
    <button type="button" id="skip-resin-dismiss" class="btn btn-sm" style="background:#F59E0B;color:#fff;border:none;white-space:nowrap;">Acknowledge &amp; Dismiss</button>
  </div>

  <!-- FaceTime badge (permanent — shown whenever facetime_requested=1) -->
  <div id="facetime-banner" style="display:none;background:#EFF6FF;border:1px solid #3B82F6;border-radius:10px;padding:16px 20px;margin-bottom:20px;align-items:center;gap:12px;">
    <span style="font-size:20px;">&#128241;</span>
    <div style="flex:1;">
      <strong style="color:#1E40AF;">Customer is open to a FaceTime / video call</strong>
      <div style="font-size:13px;color:#3B82F6;margin-top:4px;">They'd like to see their 3D resin or diamond over video. Confirm a time before calling.</div>
    </div>
    <span style="display:inline-flex;align-items:center;gap:6px;background:#1E40AF;color:#fff;font-size:12px;font-weight:700;padding:6px 14px;border-radius:20px;white-space:nowrap;">&#128241; FaceTime Requested</span>
  </div>

  <!-- Care plan purchased banner (shown/hidden by JS) -->
  <div id="care-plan-banner" style="display:none;background:#F0FDF4;border:1px solid #22C55E;border-radius:10px;padding:16px 20px;margin-bottom:20px;align-items:center;gap:12px;">
    <span style="font-size:20px;">&#128142;</span>
    <div style="flex:1;">
      <strong style="color:#15803D;">Customer purchased the Lifetime Care Plan</strong>
      <div style="font-size:13px;color:#16A34A;margin-top:4px;">Amount paid: <span id="care-plan-banner-amount"></span> &mdash; submit enrollment to Jewelers Mutual.</div>
    </div>
  </div>

  <form id="order-form">
    <input type="hidden" name="skip_resin_requested" id="skip_resin_requested" value="">
    <input type="hidden" name="facetime_requested" id="facetime_requested" value="">
    <input type="hidden" name="care_plan_purchased" id="care_plan_purchased" value="">
    <input type="hidden" name="care_plan_amount"    id="care_plan_amount"    value="">
    <input type="hidden" name="mode" id="mode" value="<?= $isNew ? 'create' : 'update' ?>">
    <input type="hidden" name="order_id" id="order_id" value="<?= htmlspecialchars($orderId) ?>">

    <!-- ── Customer Info ──────────────────────────────────────────── -->
    <div class="card<?= $isFullAdmin ? '' : ' card--readonly' ?>">
      <?php if (!$isFullAdmin): ?><fieldset disabled style="border:none;padding:0;margin:0;"><?php endif; ?>
      <div class="card-title">Customer Information</div>
      <div class="editor-grid">
        <div class="form-group">
          <label for="customer_name">Full Name *</label>
          <input type="text" id="customer_name" name="customer_name" class="form-control" <?= $isFullAdmin ? 'required' : '' ?>>
        </div>
        <div class="form-group">
          <label for="email">Email * <button type="button" class="copy-btn" onclick="copyFieldValue('email', this)">Copy</button></label>
          <input type="email" id="email" name="email" class="form-control">
        </div>
        <div class="form-group">
          <label for="phone">Phone</label>
          <input type="tel" id="phone" name="phone" class="form-control">
        </div>
        <div class="form-group">
          <label for="address">Address</label>
          <input type="text" id="address" name="address" class="form-control">
        </div>
      </div>
      <?php if ($isNew): ?>
        <div class="form-hint" style="margin-top:4px;">
          Creating a new order will send the customer a portal invitation email.
        </div>
      <?php endif; ?>
      <?php if (!$isFullAdmin): ?></fieldset><?php endif; ?>
    </div>

    <!-- ── Project Status ─────────────────────────────────────────── -->
    <div class="card">
      <div class="card-title">Project Status &amp; Timeline</div>
      <div class="editor-grid">
        <div class="form-group">
          <label for="status">Status</label>
          <select id="status" name="status" class="form-control">
            <optgroup label="Design Review">
              <option value="Design Review">Design Review</option>
              <option value="Design in Process">Design in Process</option>
            </optgroup>
            <optgroup label="Design Approval">
              <option value="Awaiting Design Approval">Awaiting Design Approval</option>
            </optgroup>
            <optgroup label="3D Model">
              <option value="3D Printing Resin/Wax Model">3D Printing Resin/Wax Model</option>
              <option value="Awaiting 3D Printed Resin Approval">Awaiting 3D Printed Resin Approval</option>
            </optgroup>
            <optgroup label="In Production">
              <option value="In Production">In Production</option>
              <option value="Casting">Casting</option>
              <option value="Setting Stones">Setting Stones</option>
            </optgroup>
            <optgroup label="Complete">
              <option value="Complete and Awaiting Payment">Complete and Awaiting Payment</option>
              <option value="Complete and Ready for Delivery">Complete and Ready for Delivery</option>
            </optgroup>
            <optgroup label="Delivered">
              <option value="Sent Overnight Mail">Sent Overnight Mail</option>
            </optgroup>
          </select>
        </div>
        <div class="form-group">
          <label for="estimated_completion">Estimated Completion Date</label>
          <input type="date" id="estimated_completion" name="estimated_completion" class="form-control">
        </div>
        <div class="form-group editor-full">
          <label for="timeline_note">Timeline Note</label>
          <input type="text" id="timeline_note" name="timeline_note" class="form-control"
                 placeholder="e.g. Stone is being set this week">
        </div>
        <div class="form-group editor-full">
          <label for="project_update">Project Update Message (shown to customer)</label>
          <textarea id="project_update" name="project_update" class="form-control" rows="4"
                    placeholder="Write a message visible to the customer about the current project stage…"></textarea>
        </div>
        <div class="form-group editor-full" id="tracking-field" style="display:none;">
          <label for="tracking_number">Tracking Number (UPS / FedEx)</label>
          <input type="text" id="tracking_number" name="tracking_number" class="form-control"
                 placeholder="e.g. 1Z999AA10123456784">
          <div class="form-hint">Shown to the customer with a copy button when status is Sent Overnight Mail.</div>
        </div>
      </div>
    </div>

    <!-- ── Payments ───────────────────────────────────────────────── -->
    <div class="card<?= $isFullAdmin ? '' : ' card--readonly' ?>">
      <?php if (!$isFullAdmin): ?><fieldset disabled style="border:none;padding:0;margin:0;"><?php endif; ?>
      <div class="card-title">Payments</div>
      <div class="editor-grid">
        <div class="form-group">
          <label for="total_estimate">Total Ring Estimate ($)</label>
          <input type="number" id="total_estimate" name="total_estimate" class="form-control"
                 min="0" step="0.01" placeholder="e.g. 4000">
          <div class="form-hint">This is the total price you quoted the customer.</div>
        </div>
        <div class="form-group">
          <label for="deposit_paid">Deposit Paid So Far ($)</label>
          <input type="number" id="deposit_paid" name="deposit_paid" class="form-control"
                 min="0" step="0.01" value="500">
        </div>
        <div class="form-group">
          <label for="progress_deposit_due">Progress Deposit Due ($)</label>
          <input type="number" id="progress_deposit_due" name="progress_deposit_due" class="form-control"
                 min="0" step="0.01" placeholder="Auto-calculated">
          <div class="payment-formula" id="formula-preview">Enter a total estimate to see the auto-calculated amount.</div>
        </div>
        <div class="form-group">
          <label for="final_payment_due">Final Payment Amount ($)</label>
          <input type="number" id="final_payment_due" name="final_payment_due" class="form-control"
                 min="0" step="0.01" placeholder="Auto-calculated">
          <div class="payment-formula" id="final-formula-preview">Enter a total estimate to see the auto-calculated amount.</div>
        </div>
        <div class="form-group">
          <label for="shipping_charge">Shipping Charge ($)</label>
          <input type="number" id="shipping_charge" name="shipping_charge" class="form-control"
                 min="0" step="0.01" placeholder="0" value="0">
          <div class="form-hint">Enter a shipping amount to show a "Pay Shipping" button to the customer.</div>
        </div>
        <div class="form-group">
          <label for="amount_paid_total">Total Paid So Far ($)</label>
          <input type="number" id="amount_paid_total" name="amount_paid_total" class="form-control"
                 min="0" step="0.01" value="500">
          <div class="form-hint">Updated automatically by Stripe webhook.</div>
        </div>
        <div class="form-group" style="display:flex;align-items:center;gap:10px;padding-top:28px;">
          <input type="checkbox" id="final_payment_enabled" name="final_payment_enabled"
                 style="width:18px;height:18px;cursor:pointer;">
          <label for="final_payment_enabled" style="font-size:14px;font-weight:600;cursor:pointer;text-transform:none;letter-spacing:0;color:var(--text);">
            Enable "Pay Final Balance" button for customer
          </label>
        </div>
      </div>
      <?php if (!$isFullAdmin): ?></fieldset><?php endif; ?>
    </div>

    <!-- ── Send Estimate ─────────────────────────────────────────── -->
    <div class="card<?= $isFullAdmin ? '' : ' card--readonly' ?>" id="estimate-card" style="display:none;">
      <?php if (!$isFullAdmin): ?><fieldset disabled style="border:none;padding:0;margin:0;"><?php endif; ?>
      <div class="card-title">Send Price Estimate to Customer</div>
      <p style="font-size:13px;color:var(--muted);margin-bottom:16px;">
        Break down the price for the customer. When you click <strong>Send Estimate Email</strong>, they'll receive a branded email with the full breakdown and a link to log in to their portal, set their password, and pay their deposit.
      </p>

      <div id="estimate-lines" style="display:flex;flex-direction:column;gap:10px;margin-bottom:14px;"></div>

      <button type="button" id="btn-add-estimate-line" class="btn btn-outline btn-sm" style="margin-bottom:18px;">+ Add Line Item</button>

      <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:14px 18px;margin-bottom:18px;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <span style="font-weight:700;font-size:15px;color:var(--text);">Estimated Total</span>
          <span id="estimate-total-display" style="font-weight:800;font-size:18px;color:var(--brand);">$0</span>
        </div>
        <div style="font-size:12px;color:var(--muted);margin-top:4px;">This will auto-fill the "Total Ring Estimate" field above when sent.</div>
      </div>

      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <button type="button" id="btn-send-estimate" class="btn btn-primary">
          Send Estimate Email
        </button>
        <span id="estimate-sent-badge" style="display:none;background:#D1FAE5;color:#065F46;border:1px solid #34D399;border-radius:6px;padding:5px 12px;font-size:13px;font-weight:600;">&#10003; Estimate Sent</span>
      </div>
      <div id="estimate-msg" style="display:none;margin-top:10px;"></div>
      <?php if (!$isFullAdmin): ?></fieldset><?php endif; ?>
    </div>

    <!-- ── 3D Model Versions ─────────────────────────────────────── -->
    <div class="card<?= $isFullAdmin ? '' : ' card--readonly' ?>">
      <?php if (!$isFullAdmin): ?><fieldset disabled style="border:none;padding:0;margin:0;"><?php endif; ?>
      <div class="card-title">3D Model Versions</div>
      <p style="font-size:13px;color:var(--muted);margin-bottom:12px;">Add named versions so the customer can select which one to approve. Each version will appear in the customer portal with an "Approve This Version" button.</p>
      <div id="versions-editor"></div>
      <div style="display:flex;align-items:center;gap:10px;margin-top:10px;flex-wrap:wrap;">
        <button type="button" class="btn btn-outline btn-sm" id="btn-add-version">+ Add Version</button>
        <button type="button" class="btn btn-sm" id="btn-reset-approval"
                style="background:#FEF3C7;color:#92400E;border:1px solid #F59E0B;display:none;">
          Reset Customer Approval
        </button>
      </div>
      <input type="hidden" id="versions_json" name="versions_json" value="[]">
      <input type="hidden" id="approved_version_id" name="approved_version_id" value="">
      <input type="hidden" id="ring_approved_notification" name="ring_approved_notification" value="">
      <?php if (!$isFullAdmin): ?></fieldset><?php endif; ?>
    </div>

    <!-- ── Ring Choices JSON ──────────────────────────────────────── -->
    <div class="card<?= $isFullAdmin ? '' : ' card--readonly' ?>">
      <?php if (!$isFullAdmin): ?><fieldset disabled style="border:none;padding:0;margin:0;"><?php endif; ?>
      <div class="card-title">Ring Builder Selections (JSON)</div>
      <div class="form-group">
        <textarea id="ring_choices_json" name="ring_choices_json" class="form-control"
                  rows="6" placeholder="[]"
                  style="font-family:monospace;font-size:12px;"></textarea>
        <div class="form-hint">Populated automatically from the ring builder submission. Can be edited if needed.</div>
      </div>
      <?php if (!$isFullAdmin): ?></fieldset><?php endif; ?>
    </div>

    <!-- ── Media ──────────────────────────────────────────────────── -->
    <?php if (!$isNew && $orderId): ?>
    <div class="card">
      <div class="card-title">Photos &amp; Videos</div>
      <div class="media-grid" id="admin-media-grid">
        <div style="color:var(--muted);font-size:13px;">Loading…</div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Save Button ────────────────────────────────────────────── -->
    <?php if ($isFullAdmin && !$isNew): ?>
    <div style="background:#F9FAFB;border:1px solid #E5E7EB;border-radius:10px;padding:16px 20px;margin-top:8px;margin-bottom:4px;">
      <div style="font-weight:700;font-size:13px;color:#374151;margin-bottom:10px;">&#9993; Status Change Email</div>
      <div style="display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap;">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;color:#374151;">
          <input type="radio" name="email_mode" value="auto" checked style="accent-color:#2D5F8A;">
          <span><strong>Auto-send</strong> — send the email automatically when status changes</span>
        </label>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;color:#374151;">
          <input type="radio" name="email_mode" value="manual" style="accent-color:#2D5F8A;">
          <span><strong>I&#39;ll email manually</strong> — skip the auto-email, I&#39;ll reach out personally</span>
        </label>
      </div>
      <div id="manual-email-hint" style="display:none;margin-top:10px;font-size:13px;color:#6B7280;">
        After saving, a <strong>mailto link</strong> will appear so you can open a draft in your email client with the customer&#39;s address pre-filled.
      </div>
    </div>
    <?php endif; ?>
    <!-- spacer so content isn't hidden behind sticky bar -->
    <div style="height:72px;"></div>
    <div id="msg-error"   class="error-msg"   style="display:none;margin-top:12px;"></div>
    <div id="msg-success" class="success-msg" style="display:none;margin-top:12px;"></div>
    <div id="manual-email-cta" style="display:none;margin-top:12px;padding:14px 18px;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:8px;font-size:14px;color:#1E40AF;">
      &#9993; <strong>Status saved.</strong> Since you chose to email manually, here is a quick link to open a draft: <a id="manual-mailto-link" href="#" style="color:#2D5F8A;font-weight:700;text-decoration:underline;">Open email draft &#8594;</a>
    </div>
  </form>

</main>

<!-- Sticky Save Bar -->
<div class="sticky-save-bar" id="sticky-save-bar">
  <div class="sticky-save-bar-inner">
    <span class="unsaved-dot" id="unsaved-dot" style="display:none;" title="Unsaved changes"></span>
    <span id="sticky-order-label" style="font-size:13px;color:var(--muted);flex:1;"></span>
    <a href="/admin-dashboard.php" class="btn btn-outline">← All Orders</a>
    <button type="submit" form="order-form" class="btn btn-primary" id="sticky-save-btn">
      <?= $isNew ? 'Create Order' : 'Save Changes' ?>
    </button>
  </div>
</div>

<!-- Edit Caption Modal -->
<div class="modal-backdrop" id="edit-caption-modal">
  <div class="modal-box" style="max-width:380px">
    <h3>Edit Caption</h3>
    <div id="edit-caption-error" class="error-msg" style="display:none"></div>
    <div class="form-group" style="margin-top:10px;">
      <input type="text" id="edit-caption-input" class="form-control" placeholder="Add a caption...">
    </div>
    <div style="display:flex;gap:10px;margin-top:14px;">
      <button type="button" id="edit-caption-save" class="btn btn-primary" style="flex:1">Save</button>
      <button type="button" id="edit-caption-cancel" class="btn btn-outline">Cancel</button>
    </div>
  </div>
</div>

<!-- Delete Confirm Modal -->
<div class="modal-backdrop" id="delete-media-modal">
  <div class="modal-box" style="max-width:340px;text-align:center">
    <h3>Remove Photo?</h3>
    <p style="margin:10px 0 18px;color:var(--muted);font-size:14px;">This will permanently remove this photo from the project.</p>
    <div style="display:flex;gap:10px;">
      <button type="button" id="delete-media-confirm" class="btn" style="flex:1;background:#e53e3e;color:#fff;border:none;">Remove</button>
      <button type="button" id="delete-media-cancel" class="btn btn-outline" style="flex:1">Cancel</button>
    </div>
  </div>
</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox">
  <div class="lightbox-inner">
    <button class="lightbox-close" id="lightbox-close">✕</button>
    <div id="lightbox-content"></div>
    <div class="lightbox-caption" id="lightbox-caption"></div>
  </div>
</div>

<!-- Upload modal (admin side) -->
<div class="modal-backdrop" id="upload-modal">
  <div class="modal-box">
    <h3>Add Photo or Video</h3>
    <div id="upload-error" class="error-msg" style="display:none"></div>
    <form id="upload-form" enctype="multipart/form-data">
      <div class="drop-zone" id="drop-zone">
        <div class="drop-icon">📷</div>
        <p id="drop-label">Click or drag a photo, video, or PDF here</p>
        <p style="font-size:11px;margin-top:4px;">JPG, PNG, GIF, WEBP, MP4, MOV, PDF — max 50MB</p>
        <div id="drop-filename-chip" style="display:none;margin-top:10px;align-items:center;gap:6px;background:#f3f4f6;border:1px solid #d1d5db;border-radius:6px;padding:5px 10px;max-width:90%;"></div>
      </div>
      <input type="file" id="upload-file" name="file" accept="image/*,video/mp4,video/quicktime,application/pdf" style="display:none">
      <div class="form-group" style="margin-top:14px;">
        <label for="upload-caption">Caption (optional)</label>
        <input type="text" id="upload-caption" class="form-control" placeholder="e.g. Diamond video from vendor">
      </div>
      <div style="display:flex;gap:10px;margin-top:4px;">
        <button type="submit" class="btn btn-primary" style="flex:1">Upload</button>
        <button type="button" class="btn btn-outline" id="upload-close">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="/assets/portal.js?v=<?= filemtime(__DIR__ . '/assets/portal.js') ?>"></script>
<script>Admin.initEditor({ adminRole: '<?= getAdminRole() ?>' });</script>
</body>
</html>
