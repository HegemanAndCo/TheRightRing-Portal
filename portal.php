<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/bootstrap.php';

requireCustomerLogin();
$email = getCurrentUserEmail();
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

<!-- Header -->
<header class="portal-header">
  <a href="https://www.therightring.com" target="_blank" rel="noopener"><img class="logo" src="https://framerusercontent.com/images/FHftFuIChaavuwoII685yqNf6A.png" alt="The Right Ring"></a>
  <div class="header-right">
    <span class="user-name"><?= htmlspecialchars($email) ?></span>
    <a href="/logout.php?redirect=change_password" class="btn btn-outline btn-sm btn-change-pw"><span>Change Password</span></a>
    <a href="/logout.php" class="btn btn-outline btn-sm">Log Out</a>
  </div>
</header>

<main class="portal-main">

  <!-- Loading state -->
  <div id="dashboard-loading" style="text-align:center;padding:60px 0;color:var(--muted);">
    <div class="spinner" style="width:32px;height:32px;border-width:3px;"></div>
    <p style="margin-top:16px;">Loading your project…</p>
  </div>

  <!-- Error state -->
  <div id="dashboard-error" class="error-msg" style="display:none;margin-top:24px;"></div>

  <!-- Main content -->
  <div id="dashboard-content" style="display:none">

    <!-- ── Status Card ──────────────────────────────────────────────── -->
    <div class="card">
      <div class="card-title">Project Status</div>
      <div id="status-steps" style="margin-bottom:16px;"></div>

      <div class="timeline-row">
        <div class="timeline-icon">📅</div>
        <div>
          <div class="timeline-label">Estimated Completion</div>
          <div class="timeline-value" id="timeline-date">TBD</div>
        </div>
      </div>

      <div id="timeline-note-wrap" style="margin-top:8px;color:var(--muted);font-size:13px;">
        <span id="timeline-note"></span>
      </div>

      <div id="last-updated-wrap" style="display:none;margin-top:6px;font-size:12px;color:#b0b8c1;">
        Last updated: <span id="last-updated-date"></span>
      </div>

      <div id="ring-sizer-note" style="display:none;margin-top:12px;padding:10px 14px;background:#FFF7ED;border:1px solid #FCD34D;border-radius:8px;font-size:13px;color:#92400E;">
        📦 Your free ring sizer is on its way! Once it arrives and you know your size, update it in your ring selections below.
      </div>

      <div id="project-update-wrap" style="margin-top:12px;">
        <div class="project-update" id="project-update"></div>
      </div>

      <div id="tracking-wrap" style="display:none;margin-top:16px;">
        <div class="timeline-label" style="margin-bottom:6px;">Tracking Number</div>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <code id="tracking-number-display" style="font-size:15px;font-weight:600;letter-spacing:0.04em;background:#f3f4f6;padding:6px 12px;border-radius:6px;"></code>
          <button id="tracking-copy-btn" class="btn btn-outline btn-sm" type="button">Copy</button>
          <a id="tracking-link-btn" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm" style="text-decoration:none;">Track Package</a>
        </div>
      </div>
    </div>

    <!-- ── Ring Choices Card ────────────────────────────────────────── -->
    <div class="card">
      <div class="card-title card-title-toggle" id="choices-toggle">
        Your Ring Design
        <span class="toggle-arrow" id="choices-arrow">&#9662;</span>
      </div>
      <div id="choices-body">
        <ul class="choices-list" id="choices-list"></ul>
        <p style="font-size:13px;color:var(--muted);margin-top:16px;">
          Need to make changes? Email your jewelry professional at
          <a href="mailto:design@therightring.com" style="color:var(--brand);text-decoration:underline;">design@therightring.com</a>
        </p>
      </div>
    </div>

    <!-- ── Media Gallery ───────────────────────────────────────────── -->
    <div class="card">
      <div class="card-title card-title-toggle" id="media-toggle">
        Photos &amp; Videos
        <span class="toggle-arrow" id="media-arrow">&#9662;</span>
      </div>
      <div id="media-body">
        <p style="font-size:13px;color:var(--muted);margin-bottom:12px;">
          Share inspiration photos or view updates from The Right Ring team.
        </p>
        <div class="media-grid" id="media-grid"></div>
      </div>
    </div>

    <!-- ── Complete Your Profile Card ─────────────────────────────── -->
    <div class="card" id="profile-complete-card" style="display:none;border:2px solid #FCD34D;background:#FFFBEB;">
      <div class="card-title" style="color:#92400E;">&#128204; Complete Your Profile</div>
      <p style="font-size:14px;color:#78350F;line-height:1.6;margin-bottom:16px;">
        We need your phone number and shipping address to move forward with your ring. Please take a moment to fill these in.
      </p>
      <div id="profile-complete-error" class="error-msg" style="display:none;margin-bottom:12px;"></div>
      <div id="profile-complete-success" class="success-msg" style="display:none;margin-bottom:12px;"></div>
      <form id="form-complete-profile">
        <div class="form-group">
          <label for="profile-phone">Phone Number</label>
          <input type="tel" id="profile-phone" class="form-control" placeholder="(555) 555-5555" autocomplete="tel">
        </div>
        <div class="form-group">
          <label for="profile-address">Shipping Address</label>
          <textarea id="profile-address" class="form-control" rows="3" placeholder="123 Main St, City, State, ZIP" autocomplete="street-address" style="resize:vertical;"></textarea>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;">Save &amp; Continue</button>
      </form>
    </div>

    <!-- ── Estimate Card ───────────────────────────────────────────── -->
    <div class="card" id="estimate-card" style="display:none;">
      <div class="card-title">Your Price Estimate</div>
      <p style="font-size:13px;color:var(--muted);margin-bottom:16px;">
        Here is the personalized estimate from The Right Ring team based on your design selections. Final pricing may vary slightly with final stone and metal details.
      </p>
      <div id="estimate-lines-display" style="margin-bottom:4px;"></div>
      <div style="border-top:2px solid #e5e7eb;padding-top:12px;margin-top:4px;display:flex;justify-content:space-between;align-items:center;">
        <span style="font-weight:700;font-size:15px;color:var(--text);">Estimated Total</span>
        <span id="estimate-total-customer" style="font-weight:800;font-size:20px;color:var(--brand);"></span>
      </div>
    </div>

    <!-- ── Payments Card ───────────────────────────────────────────── -->
    <div class="card">
      <div class="card-title">Payments</div>
      <div class="payment-rows">

        <!-- Initial deposit — paid or inquiry only -->
        <div class="payment-row" id="initial-payment-row">
          <div>
            <div class="payment-label">Initial Design Deposit</div>
            <div class="payment-status" id="initial-payment-status">Paid on submission</div>
          </div>
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span class="payment-amount" id="initial-payment-amount">$250</span>
            <span class="payment-check" id="initial-payment-check">✓</span>
            <button class="btn btn-primary btn-sm" id="btn-pay-deposit" style="display:none">Pay $250 Deposit</button>
            <div id="deposit-no-lock-note" style="display:none;font-size:12px;color:#6b7280;margin-top:6px;">No design is locked in — revisions are always included.</div>
          </div>
        </div>

        <!-- Progress deposit nudge (shown after design approval or skip resin) -->
        <div id="progress-deposit-nudge" style="display:none;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:10px;padding:16px 20px;margin-bottom:4px;">
          <div style="font-weight:700;font-size:15px;color:#1E40AF;margin-bottom:6px;">&#128230; Next Step: Pay Your Progress Deposit</div>
          <div style="font-size:14px;color:#374151;line-height:1.6;margin-bottom:12px;" id="progress-nudge-text">Your design has been approved and your ring is moving forward. To continue into the next stage, please pay your progress deposit below.</div>
          <button class="btn btn-primary btn-sm" id="btn-nudge-pay-progress">Pay Progress Deposit</button>
        </div>

        <!-- Progress deposit -->
        <div class="payment-row" id="progress-payment-row">
          <div>
            <div class="payment-label">Progress Deposit</div>
            <div class="payment-status" id="progress-status">Due after design approval</div>
          </div>
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span class="payment-amount" id="progress-amount">TBD</span>
            <button class="btn btn-primary btn-sm" id="btn-pay-progress" style="display:none">Pay Now</button>
          </div>
        </div>

        <!-- Final payment -->
        <div class="payment-row disabled" id="final-payment-row">
          <div>
            <div class="payment-label">Final Payment</div>
            <div class="payment-status" id="final-status">Available when your ring is completed</div>
          </div>
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span class="payment-amount" id="final-amount">TBD</span>
            <button class="btn btn-primary btn-sm" id="btn-pay-final" style="display:none">Pay Now</button>
          </div>
        </div>

        <!-- Care Plan offer (shown when final payment is due, before declined or purchased) -->
        <div id="care-plan-offer" style="display:none;margin-top:4px;padding:16px;background:#F8F6FF;border:1px solid #d4b8ff;border-radius:10px;">
          <div style="font-weight:700;font-size:15px;color:#232429;margin-bottom:6px;">&#128142; Add a Lifetime Care Plan?</div>
          <div style="font-size:13px;color:var(--muted);margin-bottom:4px;">Jewelers Mutual &mdash; covers repairs, re-sizing, and stone replacement for life.</div>

          <!-- Price breakdown -->
          <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px 14px;margin-bottom:10px;font-size:13px;">
            <div style="display:flex;justify-content:space-between;margin-bottom:5px;color:var(--muted);">
              <span>Plan base price</span><span id="care-plan-base-price">&mdash;</span>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:5px;color:var(--muted);">
              <span>Sales tax (7%)</span><span id="care-plan-tax">&mdash;</span>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:5px;color:var(--muted);">
              <span>Processing fee (2.9% + $0.30)</span><span id="care-plan-fee">&mdash;</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-weight:700;color:#232429;border-top:1px solid #e5e7eb;padding-top:7px;margin-top:4px;">
              <span>Care plan total</span><span id="care-plan-total">&mdash;</span>
            </div>
          </div>

          <div style="font-size:13px;color:var(--muted);margin-bottom:10px;">Under this plan, The Right Ring requires that you send your ring back to us once every two years for a proper inspection. Shipping and insurance both ways are covered by us.</div>

          <!-- What's covered dropdown -->
          <div style="margin-bottom:12px;">
            <button id="care-plan-toggle" type="button" style="background:none;border:none;color:var(--brand);font-size:12px;font-weight:600;cursor:pointer;padding:0;">
              &#9658; What does this plan cover?
            </button>
            <div id="care-plan-details" style="display:none;margin-top:8px;font-size:12px;color:var(--muted);line-height:1.7;padding:10px 12px;background:#fff;border-radius:8px;border:1px solid #e5e7eb;">
              <ul style="margin:0 0 8px 16px;padding:0;">
                <li>Ring re-sizing</li>
                <li>Refinishing and polishing</li>
                <li>Broken, bent or worn prongs</li>
                <li>Tightening of loose stones</li>
                <li>Loss of stones (including center stones) due to a defective setting, worn or broken prongs</li>
                <li>Cracked or thinning band or shank</li>
                <li>Replacement of broken clasps</li>
                <li>Replacement of cracked or chipped stones</li>
                <li>Shipping</li>
              </ul>
              <p style="margin:0;font-size:11px;">All claims are covered up to your original ring purchase price (<strong id="care-plan-ring-price"></strong>), after which the plan is considered fulfilled.</p>
            </div>
          </div>

          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button class="btn btn-primary btn-sm" id="btn-pay-care-plan">Yes, Add Plan &amp; Pay Final Balance</button>
            <button class="btn btn-outline btn-sm" id="btn-decline-care-plan">No Thanks &mdash; Just Pay Final Balance</button>
          </div>
        </div>

        <!-- Care Plan enrolled row (shown after purchase) -->
        <div class="payment-row paid" id="care-plan-row" style="display:none;">
          <div>
            <div class="payment-label">&#128142; Lifetime Care Plan</div>
            <div class="payment-status">Enrolled &mdash; Jewelers Mutual</div>
          </div>
          <div style="display:flex;align-items:center;gap:10px;">
            <span class="payment-amount" id="care-plan-amount">&mdash;</span>
            <span class="payment-check">&#10003;</span>
          </div>
        </div>

      </div>

      <div class="total-estimate" style="margin-top:16px;">
        Ring estimate: <strong id="total-estimate">TBD</strong>
      </div>

    </div>

    <!-- ── Approve Ring Card ───────────────────────────────────────── -->
    <div class="card" id="approve-ring-card">
      <div class="card-title">Approve Your Ring Design</div>
      <div id="versions-list"></div>
      <div id="approve-msg" style="display:none;margin-top:12px;"></div>
    </div>

    <!-- ── Render Portal Card ──────────────────────────────────────── -->
    <div class="render-portal-card">
      <div class="card-title">3D Interactive Model Preview</div>
      <p>View your ring in an interactive 3D preview. Use your <strong>full name</strong> as the password (exactly as it appears below).</p>
      <p id="render-portal-subtitle" style="font-size:12px;opacity:0.75;margin-top:-8px;">Estimated time for 3D models is 24–48 hours after Initial Design Deposit.</p>
      <div class="render-portal-password">
        <span id="render-portal-password"></span>
        <button class="btn btn-sm" id="btn-copy-render-pw"
                style="background:rgba(255,255,255,0.15);color:#fff;flex-shrink:0;">Copy</button>
      </div>
      <a href="https://www.therightring.com/renderportal" target="_blank" rel="noopener"
         class="btn btn-dark btn-lg" style="width:100%;justify-content:center;">
        Open Render Portal →
      </a>
    </div>

    <!-- ── FaceTime / Video Call Card ──────────────────────────────── -->
    <div class="card" id="facetime-card">
      <div class="card-title">Video Call Option</div>
      <p style="font-size:14px;color:var(--muted);margin-bottom:16px;">
        We can schedule a FaceTime or video call to show you your 3D printed resin model or your diamond in person. We will <strong>never</strong> call without confirming a time with you first.
      </p>
      <label style="display:flex;align-items:center;gap:12px;cursor:pointer;font-size:15px;font-weight:600;">
        <input type="checkbox" id="facetime-checkbox" style="width:20px;height:20px;cursor:pointer;accent-color:var(--brand);">
        Yes, I'd be open to a FaceTime or video call
      </label>
      <div id="facetime-msg" style="display:none;margin-top:12px;padding:10px 14px;background:#EFF6FF;border:1px solid #93C5FD;border-radius:8px;font-size:13px;color:#1E40AF;font-weight:600;">&#128241; Got it! The Right Ring team has been notified that you're open to a video call.</div>
    </div>

  </div><!-- /dashboard-content -->
</main>

<!-- ── Upload Modal ──────────────────────────────────────────────────── -->
<div class="modal-backdrop" id="upload-modal">
  <div class="modal-box">
    <h3>Add Photo or Video</h3>
    <div id="upload-error" class="error-msg" style="display:none"></div>
    <form id="upload-form" enctype="multipart/form-data">
      <div class="drop-zone" id="drop-zone">
        <div class="drop-icon">📷</div>
        <p id="drop-label">Click or drag a photo, video, or PDF here</p>
        <p style="font-size:11px;margin-top:4px;">JPG, PNG, GIF, WEBP, MP4, MOV, PDF — max 100MB</p>
        <div id="drop-filename-chip" style="display:none;margin-top:10px;align-items:center;gap:6px;background:#f3f4f6;border:1px solid #d1d5db;border-radius:6px;padding:5px 10px;max-width:90%;"></div>
      </div>
      <input type="file" id="upload-file" name="file" accept="image/*,video/mp4,video/quicktime,application/pdf" style="display:none">
      <div class="form-group" style="margin-top:14px;">
        <label for="upload-caption">Caption (optional)</label>
        <input type="text" id="upload-caption" class="form-control" placeholder="e.g. Inspiration for halo setting">
      </div>
      <div style="display:flex;gap:10px;margin-top:4px;">
        <button type="submit" class="btn btn-primary" style="flex:1">Upload</button>
        <button type="button" class="btn btn-outline" id="upload-close">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Edit Caption Modal ────────────────────────────────────────────── -->
<div class="modal-backdrop" id="edit-caption-modal">
  <div class="modal-box" style="max-width:380px">
    <h3>Edit Caption</h3>
    <div id="edit-caption-error" class="error-msg" style="display:none"></div>
    <div class="form-group" style="margin-top:10px;">
      <input type="text" id="edit-caption-input" class="form-control" placeholder="Add a caption...">
    </div>
    <div style="display:flex;gap:10px;margin-top:14px;">
      <button id="edit-caption-save" class="btn btn-primary" style="flex:1">Save</button>
      <button id="edit-caption-cancel" class="btn btn-outline">Cancel</button>
    </div>
  </div>
</div>

<!-- ── Delete Confirm Modal ───────────────────────────────────────────── -->
<div class="modal-backdrop" id="delete-media-modal">
  <div class="modal-box" style="max-width:340px;text-align:center">
    <h3>Remove Photo?</h3>
    <p style="margin:10px 0 18px;color:var(--muted);font-size:14px;">This will permanently remove this photo from your project.</p>
    <div style="display:flex;gap:10px;">
      <button id="delete-media-confirm" class="btn" style="flex:1;background:#e53e3e;color:#fff;border:none;">Remove</button>
      <button id="delete-media-cancel" class="btn btn-outline" style="flex:1">Cancel</button>
    </div>
  </div>
</div>

<!-- ── Approve Version Confirm Modal ─────────────────────────────────── -->
<!-- ── Skip Resin Confirm Modal ───────────────────────────────────────── -->
<div class="modal-backdrop" id="skip-resin-confirm-modal">
  <div class="modal-box" style="max-width:380px;text-align:center">
    <h3>Skip 3D Printed Resin/Wax Model?</h3>
    <p style="margin:10px 0 20px;color:var(--muted);font-size:14px;">Your project will move directly to In Production and our team will be notified.</p>
    <div style="display:flex;gap:10px;">
      <button type="button" id="skip-resin-confirm-yes" class="btn btn-primary" style="flex:1">Yes, Skip</button>
      <button type="button" id="skip-resin-confirm-cancel" class="btn btn-outline" style="flex:1">Cancel</button>
    </div>
  </div>
</div>

<div class="modal-backdrop" id="approve-confirm-modal">
  <div class="modal-box" style="max-width:380px;text-align:center">
    <h3>Approve This Design?</h3>
    <p id="approve-confirm-label" style="margin:10px 0 6px;font-weight:600;font-size:15px;"></p>
    <p style="margin:0 0 20px;color:var(--muted);font-size:14px;">Once approved, your ring will move into the next production stage. This cannot be undone.</p>
    <div style="display:flex;gap:10px;">
      <button id="approve-confirm-yes" class="btn btn-primary" style="flex:1">Yes, Approve</button>
      <button id="approve-confirm-cancel" class="btn btn-outline" style="flex:1">Cancel</button>
    </div>
  </div>
</div>

<!-- ── Lightbox ──────────────────────────────────────────────────────── -->
<div class="lightbox" id="lightbox">
  <div class="lightbox-inner">
    <button class="lightbox-close" id="lightbox-close">✕</button>
    <div id="lightbox-content"></div>
    <div class="lightbox-caption" id="lightbox-caption"></div>
  </div>
</div>

<script src="/assets/portal.js?v=<?= filemtime(__DIR__ . '/assets/portal.js') ?>"></script>
<script>Dashboard.init();</script>
</body>
</html>
