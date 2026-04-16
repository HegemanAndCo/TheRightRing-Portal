/* ── The Right Ring — Portal JavaScript ─────────────────────────────────── */

// ── Login Flow ────────────────────────────────────────────────────────────

const Login = (() => {
  let activeTab = 'initial';
  let pendingEmail = '';
  let pendingPhone4 = '';

  function init() {
    const tabBtns = document.querySelectorAll('.login-tab');
    tabBtns.forEach(btn => btn.addEventListener('click', () => {
      tabBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      activeTab = btn.dataset.tab;
      document.getElementById('form-initial').style.display = activeTab === 'initial' ? '' : 'none';
      document.getElementById('form-password').style.display = activeTab === 'password' ? '' : 'none';
      clearMsg();
    }));

    const formInitial = document.getElementById('form-initial');
    if (formInitial) formInitial.addEventListener('submit', handleInitialSubmit);

    const formPassword = document.getElementById('form-password');
    if (formPassword) formPassword.addEventListener('submit', handlePasswordSubmit);

    const formSetPw = document.getElementById('form-set-password');
    if (formSetPw) formSetPw.addEventListener('submit', handleSetPassword);

    const resendBtn = document.getElementById('btn-resend-link');
    if (resendBtn) resendBtn.addEventListener('click', handleResendLink);

    // Auto-select tab from URL param (e.g. ?tab=password after logout)
    const urlTab = new URLSearchParams(window.location.search).get('tab');
    if (urlTab) {
      const targetBtn = Array.from(tabBtns).find(b => b.dataset.tab === urlTab);
      if (targetBtn) targetBtn.click();
    }
  }

  async function handleInitialSubmit(e) {
    e.preventDefault();
    const email  = document.getElementById('login-email').value.trim();
    const phone4 = document.getElementById('login-phone4').value.trim();
    const btn    = e.target.querySelector('button[type=submit]');

    setLoading(btn, true);
    clearMsg();

    const res = await apiPost('/api/login.php', { email, phone_last4: phone4, mode: 'initial' });

    setLoading(btn, false);

    if (!res.success) {
      showError(res.error);
      return;
    }

    if (res.next === 'set_password') {
      pendingEmail = email;
      pendingPhone4 = phone4;
      document.getElementById('login-section').style.display = 'none';
      document.getElementById('set-password-section').style.display = '';
      document.getElementById('sp-email').value = email;
      document.getElementById('sp-phone4').value = phone4;
    } else {
      window.location.href = '/portal.php';
    }
  }

  async function handlePasswordSubmit(e) {
    e.preventDefault();
    const email    = document.getElementById('pw-email').value.trim();
    const password = document.getElementById('pw-password').value;
    const btn      = e.target.querySelector('button[type=submit]');

    setLoading(btn, true);
    clearMsg();

    const res = await apiPost('/api/login.php', { email, password, mode: 'password' });
    setLoading(btn, false);

    if (!res.success) { showError(res.error); return; }
    window.location.href = '/portal.php';
  }

  async function handleSetPassword(e) {
    e.preventDefault();
    const email      = document.getElementById('sp-email').value.trim();
    const tokenMode  = (document.getElementById('sp-token-mode')?.value ?? '0') === '1';
    const pw         = document.getElementById('sp-password').value;
    const confirm    = document.getElementById('sp-confirm').value;
    const btn        = e.target.querySelector('button[type=submit]');

    clearMsg();

    if (pw !== confirm) { showError('Passwords do not match.'); return; }
    if (pw.length < 8)  { showError('Password must be at least 8 characters.'); return; }

    setLoading(btn, true);
    let payload;
    if (tokenMode) {
      payload = { email, password: pw, confirm, token_mode: true };
    } else {
      const phone4 = document.getElementById('sp-phone4').value.trim();
      payload = { email, phone_last4: phone4, password: pw, confirm };
    }
    const res = await apiPost('/api/set_password.php', payload);
    setLoading(btn, false);

    if (!res.success) { showError(res.error); return; }
    window.location.href = '/portal.php';
  }

  async function handleResendLink() {
    const emailEl  = document.getElementById('resend-email');
    const errEl    = document.getElementById('resend-error');
    const successEl = document.getElementById('resend-success');
    const btn      = document.getElementById('btn-resend-link');
    const email    = emailEl?.value.trim();
    if (!email) { if (errEl) { errEl.textContent = 'Please enter your email address.'; errEl.style.display = ''; } return; }
    if (errEl)     errEl.style.display = 'none';
    if (successEl) successEl.style.display = 'none';
    setLoading(btn, true);
    const res = await apiPost('/api/resend_magic_link.php', { email });
    setLoading(btn, false);
    if (successEl) {
      successEl.textContent = 'Done! Check your inbox for a new login link. It may take a minute to arrive.';
      successEl.style.display = '';
    }
    if (emailEl) emailEl.value = '';
  }

  return { init };
})();

// ── Dashboard ─────────────────────────────────────────────────────────────

const Dashboard = (() => {
  let orderData = null;

  async function init() {
    await loadOrder();
    initUpload();
    initLightbox();
    initPaymentSuccess();
    initRenderPortal();
  }

  async function loadOrder() {
    const res = await apiFetch('/api/order.php');
    if (!res.success) {
      document.getElementById('dashboard-error').textContent = res.error || 'Could not load order.';
      document.getElementById('dashboard-error').style.display = '';
      return;
    }
    orderData = res;
    renderStatus(res.order);
    renderChoices(res.choices);
    initChoicesToggle();
    initMediaToggle();
    renderEstimate(res.order);
    renderPayments(res.order);
    renderProfilePrompt(res.order);
    renderVersions(res.order);
    renderMedia(res.media, res.order.order_id);
    renderFacetime(res.order);
    renderRenderPortal(res.order);
    document.getElementById('dashboard-loading').style.display = 'none';
    document.getElementById('dashboard-content').style.display = '';
  }

  function initChoicesToggle() {
    const toggle = document.getElementById('choices-toggle');
    const body   = document.getElementById('choices-body');
    const arrow  = document.getElementById('choices-arrow');
    if (!toggle || !body) return;
    // Restore saved state
    if (localStorage.getItem('choices_collapsed') === '1') {
      body.style.display = 'none';
      if (arrow) arrow.classList.add('collapsed');
    }
    toggle.addEventListener('click', () => {
      const hidden = body.style.display === 'none';
      body.style.display = hidden ? '' : 'none';
      if (arrow) arrow.classList.toggle('collapsed', !hidden);
      localStorage.setItem('choices_collapsed', hidden ? '0' : '1');
    });
  }

  function initMediaToggle() {
    const toggle = document.getElementById('media-toggle');
    const body   = document.getElementById('media-body');
    const arrow  = document.getElementById('media-arrow');
    if (!toggle || !body) return;
    if (localStorage.getItem('media_collapsed') === '1') {
      body.style.display = 'none';
      if (arrow) arrow.classList.add('collapsed');
    }
    toggle.addEventListener('click', () => {
      const hidden = body.style.display === 'none';
      body.style.display = hidden ? '' : 'none';
      if (arrow) arrow.classList.toggle('collapsed', !hidden);
      localStorage.setItem('media_collapsed', hidden ? '0' : '1');
    });
  }

  // Visible steps shown to the customer (6 total).
  // Each group lists all internal statuses that belong to it — the first entry is the default label,
  // but if the current status is one of the others it shows that label instead.
  const STATUS_GROUPS = [
    { label: 'Design Review',    statuses: ['Design Review', 'Design in Process'] },
    { label: 'Design Approval',  statuses: ['Awaiting Design Approval'] },
    { label: '3D Model',         statuses: ['3D Printing Resin/Wax Model', 'Awaiting 3D Printed Resin Approval'] },
    { label: 'In Production',    statuses: ['In Production', 'Casting', 'Setting Stones'] },
    { label: 'Complete',         statuses: ['Complete and Awaiting Payment', 'Complete and Ready for Delivery'] },
    { label: 'Delivered',        statuses: ['Sent Overnight Mail'] },
  ];

  // Keep the flat list for legacy lookups (ring sizer note, skip-resin check, etc.)
  const STATUS_STEPS = STATUS_GROUPS.flatMap(g => g.statuses);

  function renderStatus(order) {
    const current = order.status || 'Design Review';

    // Find which group the current status belongs to
    const currentGroupIdx = STATUS_GROUPS.findIndex(g => g.statuses.includes(current));

    const stepsEl = document.getElementById('status-steps');
    if (stepsEl) {
      stepsEl.innerHTML = STATUS_GROUPS.map((group, i) => {
        let cls = 'status-step';
        if (i < currentGroupIdx)       cls += ' status-step-done';
        else if (i === currentGroupIdx) cls += ' status-step-active';
        else                            cls += ' status-step-upcoming';

        // Show the specific sub-status label when this group is active, otherwise the group label
        const displayLabel = (i === currentGroupIdx) ? current : group.label;

        const isSkippable = group.statuses.includes('3D Printing Resin/Wax Model') && (i === currentGroupIdx) && (current === '3D Printing Resin/Wax Model');
        const skipBtn = isSkippable
          ? `<button class="status-step-skip-btn" id="skip-resin-btn" type="button">Skip</button>`
          : '';
        return `<div class="${cls}"><span class="status-step-dot"></span><span class="status-step-label">${escHtml(displayLabel)}</span>${skipBtn}</div>`;
      }).join('');

      const skipBtn = document.getElementById('skip-resin-btn');
      if (skipBtn) {
        skipBtn.addEventListener('click', handleSkipResin);
      }
    }

    document.getElementById('timeline-date').textContent =
      order.estimated_completion ? formatDate(order.estimated_completion) : '4–5½ weeks after approval';

    document.getElementById('timeline-note').textContent = order.timeline_note || '';

    const updateEl = document.getElementById('project-update');
    if (order.project_update) {
      updateEl.textContent = order.project_update;
      updateEl.style.color = '';
      updateEl.style.fontStyle = '';
    } else {
      updateEl.textContent = 'The Right Ring team will post updates here as your ring progresses. Check back soon!';
      updateEl.style.color = 'var(--muted)';
      updateEl.style.fontStyle = 'italic';
    }
    updateEl.parentElement.style.display = '';

    // Last updated timestamp
    const lastUpdatedWrap = document.getElementById('last-updated-wrap');
    const lastUpdatedDate = document.getElementById('last-updated-date');
    if (lastUpdatedWrap && order.updated_at) {
      lastUpdatedDate.textContent = formatDate(order.updated_at);
      lastUpdatedWrap.style.display = '';
    }

    // Ring sizer note — show when sizer was requested and ring is still early stage
    const ringSizerNote = document.getElementById('ring-sizer-note');
    if (ringSizerNote) {
      const choices = orderData?.choices || [];
      const ringSizeChoice = choices.find(c => c.questionId === 'ringSize');
      const sizerRequested = ringSizeChoice && ringSizeChoice.name === 'Send me a free plastic ring sizer';
      const earlyStage = ['Design Review','Design in Process','Awaiting Design Approval','3D Printing Resin/Wax Model','Awaiting 3D Printed Resin Approval'].includes(order.status);
      ringSizerNote.style.display = (sizerRequested && earlyStage) ? '' : 'none';
    }

    const trackingWrap = document.getElementById('tracking-wrap');
    const trackingDisplay = document.getElementById('tracking-number-display');
    const trackingCopyBtn = document.getElementById('tracking-copy-btn');
    const trackingLinkBtn = document.getElementById('tracking-link-btn');
    if (trackingWrap && order.tracking_number) {
      trackingDisplay.textContent = order.tracking_number;
      trackingWrap.style.display = '';
      trackingCopyBtn.onclick = () => {
        navigator.clipboard.writeText(order.tracking_number).then(() => {
          trackingCopyBtn.textContent = 'Copied!';
          setTimeout(() => { trackingCopyBtn.textContent = 'Copy'; }, 2000);
        });
      };
      if (trackingLinkBtn) {
        const ups = isUPSTracking(order.tracking_number);
        trackingLinkBtn.href = ups
          ? 'https://www.ups.com/track?loc=en_US&requester=ST/&tracknum=' + encodeURIComponent(order.tracking_number)
          : 'https://www.fedex.com/fedextrack/?trknbr=' + encodeURIComponent(order.tracking_number);
        trackingLinkBtn.textContent = ups ? 'Track on UPS' : 'Track on FedEx';
      }
    } else if (trackingWrap) {
      trackingWrap.style.display = 'none';
    }
  }

  function isUPSTracking(num) {
    // UPS tracking numbers reliably start with 1Z — numeric-only sequences are typically FedEx
    return /^1Z/i.test(num);
  }

  function choiceIcon(c) {
    const qid = c.questionId || '';
    const svgWrap = (inner) =>
      `<div class="choice-img-placeholder choice-icon-svg">${inner}</div>`;

    if (qid === 'budget') {
      return svgWrap(`<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>`);
    }
    if (qid === 'ringSize') {
      return svgWrap(`<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><circle cx="12" cy="12" r="9" stroke-width="1.5"/><circle cx="12" cy="12" r="5" stroke-width="1.5"/></svg>`);
    }
    if (qid === 'engravingText') {
      return svgWrap(`<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>`);
    }
    if (qid === 'woodgrainVinesChoice') {
      return svgWrap(`<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42"/></svg>`);
    }
    if (qid.startsWith('hiddenStone_')) {
      return svgWrap(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><polygon points="12,2 20,8 17,18 7,18 4,8" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><polygon points="12,2 20,8 12,6 4,8" fill="currentColor" opacity="0.3"/><polygon points="12,6 20,8 17,18 7,18 4,8" fill="currentColor" opacity="0.15"/></svg>`);
    }
    if (qid === 'coloredStoneSelection') {
      return svgWrap(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><polygon points="12,2 20,8 17,18 7,18 4,8" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><polygon points="12,2 20,8 12,6 4,8" fill="currentColor" opacity="0.3"/><polygon points="12,6 20,8 17,18 7,18 4,8" fill="currentColor" opacity="0.15"/></svg>`);
    }
    if (qid === 'metalType' && !c.imageUrl) {
      return svgWrap(`<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 9.563C9 9.252 9.252 9 9.563 9h4.874c.311 0 .563.252.563.563v4.874c0 .311-.252.563-.563.563H9.564A.562.562 0 0 1 9 14.437V9.564Z"/></svg>`);
    }
    if (qid === 'diamond' && c.name && c.name.includes('Expert')) {
      return svgWrap(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/></svg>`);
    }
    if (qid === 'stoneShape' && c.name && c.name.toLowerCase().includes('other')) {
      return svgWrap(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><polygon points="12,2 20,8 17,18 7,18 4,8" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><polygon points="12,2 20,8 12,6 4,8" fill="currentColor" opacity="0.3"/><polygon points="12,6 20,8 17,18 7,18 4,8" fill="currentColor" opacity="0.15"/><text x="12" y="14.5" text-anchor="middle" font-size="7" font-weight="bold" fill="currentColor" font-family="sans-serif">?</text></svg>`);
    }
    if (c.imageUrl) {
      const imgSrc = c.imageUrl.startsWith('http') ? c.imageUrl : 'https://build.therightring.com' + c.imageUrl;
      return `<div class="choice-img-wrap"><img class="choice-img" src="${escHtml(imgSrc)}" alt="${escHtml(c.name)}" loading="lazy" onerror="this.closest('.choice-img-wrap').classList.add('img-error')"></div>`;
    }
    return svgWrap(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><polygon points="12,2 20,8 17,18 7,18 4,8" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><polygon points="12,2 20,8 12,6 4,8" fill="currentColor" opacity="0.3"/><polygon points="12,6 20,8 17,18 7,18 4,8" fill="currentColor" opacity="0.15"/></svg>`);
  }

  function renderChoices(choices) {
    const container = document.getElementById('choices-list');
    if (!choices || choices.length === 0) {
      container.innerHTML = '<li style="color:var(--muted);font-size:14px;">Your design selections will appear here once your submission has been reviewed by The Right Ring team. If you have any questions, reach out to us at <a href="mailto:design@therightring.com" style="color:var(--brand);">design@therightring.com</a>.</li>';
      return;
    }

    // Check if ring size needs a dropdown (unknown/sizer requested, or not provided at all)
    const ringSizeChoice = choices.find(c => c.questionId === 'ringSize');
    const needsSizeDropdown = !ringSizeChoice || (
      ringSizeChoice.name === "I don't know the ring size yet" ||
      ringSizeChoice.name === 'Send me a free plastic ring sizer'
    );

    // Build choices list, appending a synthetic ring size entry if missing entirely
    const displayChoices = ringSizeChoice ? choices : [
      ...choices,
      { questionId: 'ringSize', questionText: 'Ring Size', name: 'Not yet provided', details: '' }
    ];

    container.innerHTML = displayChoices.map(c => {
      const isRingSizeNeedsDropdown = c.questionId === 'ringSize' && needsSizeDropdown;
      return `
      <li class="choice-item">
        ${choiceIcon(c)}
        <div style="flex:1;min-width:0;">
          <div class="choice-question">${escHtml(c.questionText || '')}</div>
          <div class="choice-answer">${escHtml(c.name || '')}</div>
          ${c.details ? `<div class="choice-detail">${escHtml(c.details)}</div>` : ''}
          ${isRingSizeNeedsDropdown ? renderRingSizeDropdown() : ''}
        </div>
      </li>`;
    }).join('');

    if (needsSizeDropdown) {
      document.getElementById('ring-size-form')?.addEventListener('submit', handleRingSizeSubmit);
    }
  }

  function renderRingSizeDropdown() {
    const fractions = ['', ' 1/8', ' 1/4', ' 3/8', ' 1/2', ' 5/8', ' 3/4', ' 7/8'];
    let options = '<option value="">Select a size</option>';
    // Realistic range: size 3 (index 24) to size 13 (index 104)
    for (let i = 24; i <= 104; i++) {
      const whole = Math.floor(i / 8);
      const eighth = i % 8;
      if (eighth === 0 && i > 24) options += '<option disabled>\u2500\u2500\u2500\u2500\u2500\u2500</option>';
      const val = `${whole}${fractions[eighth]}`;
      options += `<option value="${val}">${val}</option>`;
    }
    return `
      <form id="ring-size-form" style="margin-top:8px;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
          <select id="ring-size-select" class="form-control" style="flex:1;min-width:140px;font-size:14px;padding:8px 10px;">
            ${options}
          </select>
          <button type="submit" class="btn btn-primary btn-sm" style="white-space:nowrap;min-height:38px;">Update Size</button>
        </div>
        <span id="ring-size-msg" style="font-size:12px;color:var(--muted);display:none;margin-top:4px;display:block;"></span>
        <div style="font-size:11px;color:var(--muted);margin-top:4px;">Not sure of your size? <a href="https://www.therightring.com" target="_blank" style="color:var(--brand);">Contact us</a> and we will send you a free ring sizer.</div>
      </form>`;
  }

  async function handleRingSizeSubmit(e) {
    e.preventDefault();
    const select = document.getElementById('ring-size-select');
    const msg    = document.getElementById('ring-size-msg');
    const btn    = e.target.querySelector('button[type=submit]');
    const size   = select.value;
    if (!size) { msg.textContent = 'Please select a size.'; msg.style.display = ''; return; }
    setLoading(btn, true);
    const res = await apiPost('/api/update_ring_size.php', { ring_size: size });
    setLoading(btn, false);
    if (res.success) {
      msg.textContent = 'Size saved!';
      msg.style.color = 'var(--success, green)';
      msg.style.display = '';
      // Update the displayed answer
      const answerEl = e.target.closest('li')?.querySelector('.choice-answer');
      if (answerEl) answerEl.textContent = size;
      e.target.style.display = 'none';
    } else {
      msg.textContent = res.error || 'Could not save.';
      msg.style.display = '';
    }
  }

  function renderEstimate(order) {
    const card = document.getElementById('estimate-card');
    if (!card) return;
    const raw = order.estimate_json || '';
    if (!raw) { card.style.display = 'none'; return; }
    let lines;
    try { lines = JSON.parse(raw); } catch(e) { card.style.display = 'none'; return; }
    if (!lines || !lines.length) { card.style.display = 'none'; return; }

    const container = document.getElementById('estimate-lines-display');
    const totalEl   = document.getElementById('estimate-total-customer');
    if (!container || !totalEl) return;

    let total = 0;
    container.innerHTML = lines.map(l => {
      const amt = parseFloat(l.amount) || 0;
      total += amt;
      return `<div style="display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid #f3f4f6;font-size:14px;">
        <span style="color:#374151;">${escHtml(l.label || '')}</span>
        <span style="font-weight:600;color:#232429;">$${fmt(amt)}</span>
      </div>`;
    }).join('');

    totalEl.textContent = '$' + fmt(total);
    card.style.display = '';
  }

  function renderPayments(order) {
    const total         = order.total_estimate || 0;
    const progDue       = order.progress_deposit_due || 0;
    const finalDue      = order.final_payment_due || 0;
    const depositPaid   = order.deposit_paid || 0;
    const paidTotal     = order.amount_paid_total || 0;
    const finalEnabled  = order.final_payment_enabled;
    const shippingCharge = parseFloat(order.shipping_charge) || 0;

    // Total estimate header
    document.getElementById('total-estimate').textContent = total ? '$' + fmt(total) : 'TBD';

    // Initial deposit row — only show as paid if deposit was actually collected
    const initRow    = document.getElementById('initial-payment-row');
    const initAmount = document.getElementById('initial-payment-amount');
    const initStatus = document.getElementById('initial-payment-status');
    const initCheck  = document.getElementById('initial-payment-check');
    const depositBtn = document.getElementById('btn-pay-deposit');
    if (depositPaid > 0) {
      initRow.className = 'payment-row paid';
      initAmount.textContent = '$' + fmtMoney(depositPaid);
      initStatus.textContent = 'Paid on submission';
      initCheck.style.display = '';
      if (depositBtn) depositBtn.style.display = 'none';
    } else {
      initRow.className = 'payment-row due';
      initAmount.textContent = '$250';
      initStatus.textContent = 'Pay to receive your interactive 3D model and begin your custom design';
      initCheck.style.display = 'none';
      if (depositBtn) depositBtn.style.display = '';
      const depositNote = document.getElementById('deposit-no-lock-note');
      if (depositNote) depositNote.style.display = '';
    }

    // Progress deposit row + final row — only visible once initial deposit is paid
    const progRow  = document.getElementById('progress-payment-row');
    const finalRow = document.getElementById('final-payment-row');
    const nudge    = document.getElementById('progress-deposit-nudge');

    // Progress deposit nudge — wire button regardless
    const nudgeBtn = document.getElementById('btn-nudge-pay-progress');
    if (nudgeBtn && !nudgeBtn.dataset.wired) {
      nudgeBtn.dataset.wired = '1';
      nudgeBtn.addEventListener('click', () => startPayment('progress'));
    }

    if (depositPaid <= 0) {
      // Initial deposit not yet paid — hide progress and final rows entirely
      progRow.style.display = 'none';
      finalRow.style.display = 'none';
      if (nudge) nudge.style.display = 'none';
    } else {
      progRow.style.display = '';
      finalRow.style.display = '';

      if (progDue > 0) {
        document.getElementById('progress-amount').textContent = '$' + fmt(progDue);
        document.getElementById('progress-status').textContent = 'Due now \u2014 keeps your project moving forward';
        document.getElementById('btn-pay-progress').style.display = '';
        progRow.className = 'payment-row due';
        // Show nudge if design has already been approved
        const progressStatuses = ['3D Printing Resin/Wax Model', 'Awaiting 3D Printed Resin Approval', 'In Production', 'Casting', 'Setting Stones'];
        if (nudge && progressStatuses.includes(order.status)) nudge.style.display = '';
      } else {
        const progPaid = paidTotal >= (total / 2) && total > 0;
        document.getElementById('progress-amount').textContent = progPaid ? 'Paid \u2713' : 'TBD';
        document.getElementById('progress-status').textContent = progPaid ? 'Paid' : 'Due after design approval';
        document.getElementById('btn-pay-progress').style.display = 'none';
        progRow.className = progPaid ? 'payment-row paid' : 'payment-row';
        if (nudge) nudge.style.display = 'none';
      }
    }

    if (finalDue > 0 && finalEnabled) {
      const finalTotal = finalDue + shippingCharge;
      document.getElementById('final-amount').textContent = '$' + fmt(finalTotal);
      if (shippingCharge > 0) {
        document.getElementById('final-status').textContent = 'Includes $' + fmt(shippingCharge) + ' shipping';
      }
      // btn-pay-final visibility is controlled by care plan logic below
      document.getElementById('btn-pay-final').style.display = 'none';
      finalRow.className = 'payment-row due';
    } else if (finalDue > 0) {
      document.getElementById('final-amount').textContent = '$' + fmt(finalDue);
      document.getElementById('btn-pay-final').style.display = 'none';
      finalRow.className = 'payment-row disabled';
      document.getElementById('final-status').textContent = 'Available when your ring is completed';
    } else {
      document.getElementById('final-amount').textContent = 'TBD';
      document.getElementById('btn-pay-final').style.display = 'none';
      finalRow.className = 'payment-row disabled';
    }

    // Care plan
    const carePlanOffer   = document.getElementById('care-plan-offer');
    const carePlanRow     = document.getElementById('care-plan-row');
    const carePlanAmtEl   = document.getElementById('care-plan-amount');
    const carePlanBaseEl  = document.getElementById('care-plan-base-price');
    const finalPayBtn     = document.getElementById('btn-pay-final');

    if (finalEnabled && total > 0 && finalDue > 0) {
      if (order.care_plan_purchased === '1') {
        // Already enrolled — show enrolled row, hide offer, show final as paid
        if (carePlanRow) carePlanRow.style.display = '';
        if (carePlanAmtEl) carePlanAmtEl.textContent = '$' + fmtMoney(parseFloat(order.care_plan_amount || 0));
        if (carePlanOffer) carePlanOffer.style.display = 'none';
        if (finalPayBtn) finalPayBtn.style.display = 'none';
        finalRow.className = 'payment-row paid';
        document.getElementById('final-status').textContent = 'Paid \u2713';
      } else {
        // Show care plan offer first; hide direct final pay button until declined
        if (carePlanOffer) carePlanOffer.style.display = '';
        if (finalPayBtn) finalPayBtn.style.display = 'none';
        const basePrice = getCarePlanBasePrice(total);
        if (basePrice !== null) {
          const tax        = Math.round(basePrice * 0.07 * 100) / 100;
          const pretax     = basePrice + tax;
          const stripeFee  = Math.round((pretax * 0.029 + 0.30) * 100) / 100;
          const planTotal  = pretax + stripeFee;
          if (carePlanBaseEl) carePlanBaseEl.textContent = '$' + fmtMoney(basePrice);
          const taxEl  = document.getElementById('care-plan-tax');
          const feeEl  = document.getElementById('care-plan-fee');
          const totEl  = document.getElementById('care-plan-total');
          if (taxEl)  taxEl.textContent  = '$' + fmtMoney(tax);
          if (feeEl)  feeEl.textContent  = '$' + fmtMoney(stripeFee);
          if (totEl)  totEl.textContent  = '$' + fmtMoney(planTotal);
          // Update care plan button label to include shipping if applicable
          const cpBtn2 = document.getElementById('btn-pay-care-plan');
          if (cpBtn2 && shippingCharge > 0) {
            cpBtn2.textContent = 'Yes, Add Plan \u2014 Pay Final Balance + Shipping';
          }
        }
        const ringPriceEl = document.getElementById('care-plan-ring-price');
        if (ringPriceEl) ringPriceEl.textContent = '$' + fmt(total);
      }
    }

    // Pay buttons
    document.getElementById('btn-pay-deposit').addEventListener('click', () => startPayment('deposit'));
    document.getElementById('btn-pay-progress').addEventListener('click', () => startPayment('progress'));
    document.getElementById('btn-pay-final').addEventListener('click', () => startPayment('final'));
    const cpBtn = document.getElementById('btn-pay-care-plan');
    if (cpBtn) cpBtn.addEventListener('click', () => startPayment('final-with-care-plan'));
    const carePlanToggle  = document.getElementById('care-plan-toggle');
    const carePlanDetails = document.getElementById('care-plan-details');
    if (carePlanToggle && carePlanDetails) {
      carePlanToggle.addEventListener('click', () => {
        const open = carePlanDetails.style.display !== 'none';
        carePlanDetails.style.display = open ? 'none' : '';
        carePlanToggle.innerHTML = (open ? '\u25b8' : '\u25be') + ' What does this plan cover?';
      });
    }
    const declineBtn = document.getElementById('btn-decline-care-plan');
    if (declineBtn) {
      if (shippingCharge > 0) {
        declineBtn.textContent = 'No Thanks \u2014 Just Pay Final Balance + Shipping';
      }
      declineBtn.addEventListener('click', () => {
        if (carePlanOffer) carePlanOffer.style.display = 'none';
        if (finalPayBtn) finalPayBtn.style.display = '';
      });
    }
  }

  function getCarePlanBasePrice(ringTotal) {
    const tiers = [
      [9.99,99.99,29.99],[100,199.99,49.99],[200,349.99,79.99],
      [350,499.99,99.99],[500,749.99,119.99],[750,999.99,169.99],
      [1000,1499.99,214.99],[1500,1999.99,249.99],[2000,2499.99,269.99],
      [2500,2999.99,299.99],[3000,3999.99,349.99],[4000,4999.99,399.99],
      [5000,5999.99,439.99],[6000,7999.99,499.99],[8000,9999.99,579.99],
      [10000,14999.99,799.99],[15000,19999.99,999.99],[20000,29999.99,1349.99],
    ];
    for (const [from, to, price] of tiers) {
      if (ringTotal >= from && ringTotal <= to) return price;
    }
    return null;
  }

  async function startPayment(type) {
    const btn = document.getElementById('btn-pay-' + type);
    setLoading(btn, true);
    const res = await apiPost('/api/create_payment.php', { payment_type: type });
    setLoading(btn, false);
    if (res.success && res.url) {
      window.location.href = res.url;
    } else {
      alert(res.error || 'Payment could not be started. Please try again.');
    }
  }

  function renderFacetime(order) {
    const cb = document.getElementById('facetime-checkbox');
    if (!cb || cb.dataset.listenerAttached) return;
    cb.dataset.listenerAttached = '1';

    const storageKey = 'facetime_' + (order.order_id || 'unknown');
    const msg = document.getElementById('facetime-msg');

    // Server is authoritative; fall back to localStorage only while server value is loading
    const serverVal = order.facetime_requested === '1';
    const localVal  = localStorage.getItem(storageKey) === '1';
    cb.checked = serverVal || localVal;
    // Keep localStorage in sync with server
    localStorage.setItem(storageKey, cb.checked ? '1' : '0');
    if (msg) msg.style.display = cb.checked ? 'block' : 'none';

    cb.addEventListener('change', async () => {
      if (!cb.checked) {
        // Confirm before removing preference
        const confirmed = confirm('Remove your video call preference? The Right Ring team will no longer be notified that you are open to a call.');
        if (!confirmed) { cb.checked = true; return; }
      }
      localStorage.setItem(storageKey, cb.checked ? '1' : '0');
      if (msg) msg.style.display = cb.checked ? 'block' : 'none';
      await apiPost('/api/facetime_preference.php', { facetime_requested: cb.checked });
    });
  }

  function renderMedia(mediaList, orderId) {
    const grid = document.getElementById('media-grid');
    grid.innerHTML = '';

    if (!mediaList || mediaList.length === 0) {
      grid.innerHTML = '<div class="media-empty" style="grid-column:1/-1">No photos or videos yet.</div>';
    } else {
      mediaList.forEach(m => {
        const item = document.createElement('div');
        item.className = 'media-item';

        const isVideo = m.drive_file_id && m.filename.match(/\.(mp4|mov|avi)$/i);
        const isPdf   = m.filename.match(/\.pdf$/i);
        const captionHtml = m.caption
          ? `<span class="media-caption-label">${escHtml(m.caption)}</span>`
          : '';
        const canDelete = m.uploader === 'customer';
        const deleteBtnHtml = canDelete ? `<button class="media-delete-btn" title="Remove">✕</button>` : '';
        if (isPdf) {
          item.innerHTML = `
            <a href="${escHtml(m.thumbnail_url)}" target="_blank" rel="noopener" style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;text-decoration:none;color:inherit;">
              <div style="font-size:36px;margin-bottom:6px;">📄</div>
              <div style="font-size:11px;color:var(--muted);text-align:center;padding:0 6px;word-break:break-all;">${escHtml(m.caption || m.filename)}</div>
            </a>
            <span class="media-badge">${escHtml(m.uploader)}</span>
            ${deleteBtnHtml}
            <button class="media-edit-btn" title="Edit caption">✎</button>`;
        } else if (isVideo) {
          item.innerHTML = `
            <img src="${escHtml(m.thumbnail_url)}" alt="${escHtml(m.caption || m.filename)}" loading="lazy">
            <div class="media-play">▶</div>
            <span class="media-badge">${escHtml(m.uploader)}</span>
            ${captionHtml}
            ${deleteBtnHtml}
            <button class="media-edit-btn" title="Edit caption">✎</button>`;
          item.querySelector('img').addEventListener('click', () => openLightboxVideo(m.drive_file_id, m.caption));
        } else {
          item.innerHTML = `
            <img src="${escHtml(m.thumbnail_url)}" alt="${escHtml(m.caption || m.filename)}" loading="lazy">
            <span class="media-badge">${escHtml(m.uploader)}</span>
            ${captionHtml}
            ${deleteBtnHtml}
            <button class="media-edit-btn" title="Edit caption">✎</button>`;
          item.querySelector('img').addEventListener('click', () => openLightboxImage(m.thumbnail_url, m.caption));
        }

        if (canDelete) {
          item.querySelector('.media-delete-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            openDeleteMediaModal(m.media_id, orderId);
          });
        }
        item.querySelector('.media-edit-btn').addEventListener('click', (e) => {
          e.stopPropagation();
          openEditCaptionModal(m.media_id, orderId, m.caption || '');
        });

        grid.appendChild(item);
      });
    }

    // Upload button
    const uploadBtn = document.createElement('div');
    uploadBtn.className = 'media-upload-btn';
    uploadBtn.innerHTML = '+';
    uploadBtn.setAttribute('title', 'Add photo or video');
    uploadBtn.addEventListener('click', () => openUploadModal(orderId));
    grid.appendChild(uploadBtn);
  }

  // ── Edit caption modal ──────────────────────────────────────────────
  // Edit caption and delete modals are initialised globally below (after refreshMedia)

  function renderVersions(order) {
    const container = document.getElementById('versions-list');
    if (!container) return;

    let versions = [];
    try { versions = JSON.parse(order.versions_json || '[]'); } catch(e) {}

    const approvedId = order.approved_version_id || '';

    if (!versions.length) {
      container.innerHTML = '<p style="color:var(--muted);font-size:14px;">Your 3D model versions will appear here once ready. Estimated time is 24–48 hours after your Initial Design Deposit.</p>';
      // Show the 3D subtitle since no versions yet
      const sub3d = document.getElementById('render-portal-subtitle');
      if (sub3d) sub3d.style.display = '';
      return;
    }

    // Hide the 3D subtitle when versions exist
    const sub3d = document.getElementById('render-portal-subtitle');
    if (sub3d) sub3d.style.display = 'none';

    container.innerHTML = versions.map(v => {
      const isApproved = approvedId && v.id === approvedId;
      const isWhitedOut = approvedId && v.id !== approvedId;
      return `
      <div class="version-item" style="${isWhitedOut ? 'opacity:0.35;pointer-events:none;' : ''}">
        <div class="version-label">${escHtml(v.label || v.id)}</div>
        ${isApproved
          ? `<button class="btn btn-sm btn-approved" disabled>Approved ✓</button>`
          : `<button class="btn btn-primary btn-sm btn-approve" data-id="${escHtml(v.id)}">Approve This Version</button>`
        }
      </div>`;
    }).join('');

    container.querySelectorAll('.btn-approve').forEach(btn => {
      btn.addEventListener('click', () => openApproveConfirm(btn.dataset.id, btn));
    });
  }

  function openApproveConfirm(versionId, btn) {
    const modal     = document.getElementById('approve-confirm-modal');
    const labelEl   = document.getElementById('approve-confirm-label');
    const yesBtn    = document.getElementById('approve-confirm-yes');
    const cancelBtn = document.getElementById('approve-confirm-cancel');
    if (!modal) { handleApprove(versionId, btn); return; }

    // Find the version label from the button's parent
    const versionLabel = btn.closest('.version-item')?.querySelector('.version-label')?.textContent || versionId;
    labelEl.textContent = versionLabel;
    modal.classList.add('open');

    const close = () => modal.classList.remove('open');
    cancelBtn.onclick = close;
    modal.onclick = e => { if (e.target === modal) close(); };
    yesBtn.onclick = () => { close(); handleApprove(versionId, btn); };
  }

  async function handleApprove(versionId, btn) {
    const msgEl = document.getElementById('approve-msg');
    setLoading(btn, true);
    const res = await apiPost('/api/approve_ring.php', { version_id: versionId });
    setLoading(btn, false);
    if (res.success) {
      msgEl.textContent = `You have approved "${res.version_label}". Your project is moving forward and The Right Ring Team has been notified.`;
      msgEl.className = 'success-msg';
      msgEl.style.display = '';
      // Update orderData and re-render status bar immediately
      if (orderData && orderData.order) {
        orderData.order.approved_version_id = versionId;
        orderData.order.status = '3D Printing Resin/Wax Model';
        renderStatus(orderData.order);
      }
      // Change clicked button to "Approved ✓", white out other version items
      document.querySelectorAll('.version-item').forEach(item => {
        const itemBtn = item.querySelector('.btn-approve');
        if (itemBtn && itemBtn.dataset.id === versionId) {
          itemBtn.textContent = 'Approved ✓';
          itemBtn.classList.remove('btn-primary');
          itemBtn.classList.add('btn-approved');
          itemBtn.disabled = true;
        } else {
          item.style.opacity = '0.35';
          item.style.pointerEvents = 'none';
        }
      });
      // Prompt for progress deposit if one is owed
      showProgressNudge('Your design has been approved and your ring is moving forward. To continue into the next stage, please pay your progress deposit below.');
    } else {
      msgEl.textContent = res.error || 'Something went wrong. Please try again.';
      msgEl.className = 'error-msg';
      msgEl.style.display = '';
    }
  }

  function renderRenderPortal(order) {
    document.getElementById('render-portal-password').textContent = order.customer_name || '';
  }

  function renderProfilePrompt(order) {
    const card = document.getElementById('profile-complete-card');
    if (!card) return;
    const depositPaid = parseFloat(order.deposit_paid) || 0;
    const hasPhone   = (order.phone   || '').trim().length > 0;
    const hasAddress = (order.address || '').trim().length > 0;
    if (depositPaid <= 0 || (hasPhone && hasAddress)) {
      card.style.display = 'none';
      return;
    }
    // Pre-fill any existing values
    const phoneEl   = document.getElementById('profile-phone');
    const addressEl = document.getElementById('profile-address');
    if (phoneEl   && order.phone)   phoneEl.value   = order.phone;
    if (addressEl && order.address) addressEl.value = order.address;
    card.style.display = '';

    const form    = document.getElementById('form-complete-profile');
    const errEl   = document.getElementById('profile-complete-error');
    const succEl  = document.getElementById('profile-complete-success');
    if (!form || form.dataset.wired) return;
    form.dataset.wired = '1';
    form.addEventListener('submit', async e => {
      e.preventDefault();
      const phone   = phoneEl.value.trim();
      const address = addressEl.value.trim();
      if (!phone || !address) {
        errEl.textContent = 'Please fill in both fields.';
        errEl.style.display = '';
        return;
      }
      const btn = form.querySelector('button[type=submit]');
      setLoading(btn, true);
      errEl.style.display = 'none';
      const res = await apiPost('/api/update_profile.php', { phone, address });
      setLoading(btn, false);
      if (res.success) {
        succEl.textContent = 'Profile saved — thank you!';
        succEl.style.display = '';
        form.style.display = 'none';
        setTimeout(() => { card.style.display = 'none'; }, 2000);
      } else {
        errEl.textContent = res.error || 'Could not save. Please try again.';
        errEl.style.display = '';
      }
    });
  }

  function initPaymentSuccess() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('payment_success') === '1') {
      const type = params.get('type') || '';
      const labels = {
        'deposit':             'Your $250 design deposit was received',
        'progress':            'Your progress deposit was received',
        'final':               'Your final payment was received',
        'care-plan':           'Your Lifetime Care Plan payment was received',
        'final-with-care-plan':'Your final payment and Lifetime Care Plan were received',
        'shipping':            'Your shipping payment was received',
      };
      const msg = (labels[type] || 'Your payment was received') + ' \u2014 thank you! Your project is being updated.';
      showBanner(msg, 'success');
      history.replaceState({}, '', '/portal.php');
      if (type === 'deposit') {
        // After deposit, scroll to profile card if it becomes visible
        setTimeout(() => {
          const card = document.getElementById('profile-complete-card');
          if (card && card.style.display !== 'none') {
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        }, 800);
      }
    }
    if (params.get('payment_cancelled') === '1') {
      showBanner('Payment was cancelled \u2014 no charge was made. You can try again any time.', 'info');
      history.replaceState({}, '', '/portal.php');
    }
  }

  async function refreshMedia() {
    const res = await apiFetch('/api/order.php');
    if (res.success) renderMedia(res.media, res.order.order_id);
  }

  function handleSkipResin() {
    const modal     = document.getElementById('skip-resin-confirm-modal');
    const yesBtn    = document.getElementById('skip-resin-confirm-yes');
    const cancelBtn = document.getElementById('skip-resin-confirm-cancel');
    if (!modal) return;

    modal.classList.add('open');
    const close = () => modal.classList.remove('open');
    cancelBtn.onclick = close;
    modal.onclick = e => { if (e.target === modal) close(); };
    yesBtn.onclick = async () => {
      close();
      const btn = document.getElementById('skip-resin-btn');
      if (btn) setLoading(btn, true);
      const res = await apiPost('/api/skip_resin.php', {});
      if (btn) setLoading(btn, false);
      if (res.success) {
        await loadOrder();
        showBanner('Request sent — our team will be notified and will move your project forward.', 'success');
        // Prompt for progress deposit if one is owed
        showProgressNudge('You have chosen to skip the 3D model step and your ring is moving directly into production. Please pay your progress deposit to keep things on track.');
      } else {
        showBanner(res.error || 'Could not submit request. Please try again.', 'error');
      }
    };
  }

  function showProgressNudge(text) {
    const nudge     = document.getElementById('progress-deposit-nudge');
    const nudgeText = document.getElementById('progress-nudge-text');
    const progDue    = orderData?.order?.progress_deposit_due || 0;
    const depositPaid = orderData?.order?.deposit_paid || 0;
    const paidTotal  = orderData?.order?.amount_paid_total || 0;
    const halfTotal  = (orderData?.order?.total_estimate || 0) / 2;
    // Only show if initial deposit is paid and a progress deposit is still owed
    if (depositPaid <= 0) return;
    if (progDue <= 0 && paidTotal >= halfTotal) return;
    if (!nudge) return;
    if (nudgeText && text) nudgeText.textContent = text;
    nudge.style.display = '';
    nudge.scrollIntoView({ behavior: 'smooth', block: 'center' });
    const nudgeBtn = document.getElementById('btn-nudge-pay-progress');
    if (nudgeBtn && !nudgeBtn.dataset.wired) {
      nudgeBtn.dataset.wired = '1';
      nudgeBtn.addEventListener('click', () => startPayment('progress'));
    }
  }

  return { init, refreshMedia };
})();

// ── Upload Modal ──────────────────────────────────────────────────────────

function initUpload() {
  const modal   = document.getElementById('upload-modal');
  const dropZone = document.getElementById('drop-zone');
  const fileInput = document.getElementById('upload-file');
  const btnClose  = document.getElementById('upload-close');
  const form      = document.getElementById('upload-form');

  if (!modal || modal.dataset.uploadInited) return;
  modal.dataset.uploadInited = '1';

  btnClose.addEventListener('click', () => modal.classList.remove('open'));
  modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('open'); });

  dropZone.addEventListener('click', () => fileInput.click());
  dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragging'); });
  dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragging'));
  dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('dragging');
    if (e.dataTransfer.files[0]) {
      fileInput.files = e.dataTransfer.files;
      const f  = e.dataTransfer.files[0];
      const mb = (f.size / (1024 * 1024)).toFixed(1);
      updateDropLabel(`${f.name} (${mb} MB)`);
    }
  });

  fileInput.addEventListener('change', () => {
    if (fileInput.files[0]) {
      const f    = fileInput.files[0];
      const mb   = (f.size / (1024 * 1024)).toFixed(1);
      updateDropLabel(`${f.name} (${mb} MB)`);
    }
  });

  form.addEventListener('submit', async e => {
    e.preventDefault();
    if (!fileInput.files[0]) { showModalError('Please choose a file.'); return; }
    const file = fileInput.files[0];
    const validTypes = ['image/jpeg','image/png','image/webp','image/gif','video/mp4','video/quicktime','video/x-msvideo','application/pdf'];
    const validExts  = /\.(jpe?g|png|webp|gif|mp4|mov|avi|pdf)$/i;
    if (!validTypes.includes(file.type) && !validExts.test(file.name)) { showModalError('Invalid file type. Please upload a JPG, PNG, WEBP, GIF, MP4, MOV, or PDF file.'); return; }
    const maxMB = 100;
    if (file.size > maxMB * 1024 * 1024) { showModalError(`File is too large. Maximum size is ${maxMB}MB.`); return; }

    const btn     = form.querySelector('button[type=submit]');
    const orderId = form.dataset.orderId;
    const caption = document.getElementById('upload-caption').value.trim();

    setLoading(btn, true);
    const fd = new FormData();
    fd.append('file', fileInput.files[0]);
    fd.append('order_id', orderId);
    fd.append('caption', caption);

    const res = await fetch('/api/upload_media.php', { method: 'POST', credentials: 'same-origin', body: fd }).then(r => r.json()).catch(() => ({ success: false, error: 'Upload failed.' }));
    setLoading(btn, false);

    if (!res.success) { showModalError(res.error); return; }

    modal.classList.remove('open');
    await refreshMedia();
  });
}

async function refreshMedia() {
  if (document.getElementById('admin-media-grid')) {
    await Admin.refreshAdminMedia();
  } else {
    await Dashboard.refreshMedia();
  }
}

function openUploadModal(orderId) {
  const modal = document.getElementById('upload-modal');
  const form  = document.getElementById('upload-form');
  if (!modal) return;
  form.dataset.orderId = orderId;
  document.getElementById('upload-caption').value = '';
  const dropLabel = document.getElementById('drop-label');
  if (dropLabel) { dropLabel.textContent = 'Click or drag a photo, video, or PDF here'; dropLabel.style.display = ''; }
  document.getElementById('upload-file').value = '';
  const chip = document.getElementById('drop-filename-chip');
  if (chip) { chip.innerHTML = ''; chip.style.display = 'none'; }
  clearModalError();
  modal.classList.add('open');
}

function updateDropLabel(name) {
  const el = document.getElementById('drop-label');
  if (el) el.style.display = 'none';
  const chipWrap = document.getElementById('drop-filename-chip');
  if (!chipWrap) return;
  chipWrap.style.display = 'flex';
  chipWrap.innerHTML = `<span class="drop-chip-name">${escHtml(name)}</span><button type="button" class="drop-chip-remove" title="Remove file">✕</button>`;
  chipWrap.querySelector('.drop-chip-remove').addEventListener('click', () => {
    const fi = document.getElementById('upload-file');
    if (fi) fi.value = '';
    chipWrap.style.display = 'none';
    chipWrap.innerHTML = '';
    const lbl = document.getElementById('drop-label');
    if (lbl) lbl.style.display = '';
  });
}

function showModalError(msg) {
  const el = document.getElementById('upload-error');
  if (el) { el.textContent = msg; el.style.display = ''; }
}

function clearModalError() {
  const el = document.getElementById('upload-error');
  if (el) el.style.display = 'none';
}

// ── Lightbox ──────────────────────────────────────────────────────────────

function initLightbox() {
  const lb = document.getElementById('lightbox');
  if (!lb || lb.dataset.lbInited) return;
  lb.dataset.lbInited = '1';
  document.getElementById('lightbox-close').addEventListener('click', closeLightbox);
  lb.addEventListener('click', e => { if (e.target === lb) closeLightbox(); });
}

function openLightboxImage(src, caption) {
  const lb = document.getElementById('lightbox');
  document.getElementById('lightbox-content').innerHTML = `<img src="${escHtml(src)}" alt="${escHtml(caption || '')}">`;
  document.getElementById('lightbox-caption').textContent = caption || '';
  lb.classList.add('open');
}

function openLightboxVideo(driveFileId, caption) {
  const lb = document.getElementById('lightbox');
  const src = `https://drive.google.com/file/d/${driveFileId}/preview`;
  document.getElementById('lightbox-content').innerHTML = `<iframe src="${src}" frameborder="0" allowfullscreen></iframe>`;
  document.getElementById('lightbox-caption').textContent = caption || '';
  lb.classList.add('open');
}

function closeLightbox() {
  const lb = document.getElementById('lightbox');
  lb.classList.remove('open');
  document.getElementById('lightbox-content').innerHTML = '';
}

// ── Edit Caption Modal (global — works for both admin and customer) ────────

(function setupEditCaptionModal() {
  const modal     = document.getElementById('edit-caption-modal');
  const input     = document.getElementById('edit-caption-input');
  const saveBtn   = document.getElementById('edit-caption-save');
  const cancelBtn = document.getElementById('edit-caption-cancel');
  const errEl     = document.getElementById('edit-caption-error');
  if (!modal) return;

  let _mediaId = '', _orderId = '';

  window.openEditCaptionModal = function(mediaId, orderId, currentCaption) {
    _mediaId = mediaId; _orderId = orderId;
    input.value = currentCaption;
    errEl.style.display = 'none';
    modal.classList.add('open');
    setTimeout(() => input.focus(), 50);
  };

  saveBtn.addEventListener('click', async () => {
    setLoading(saveBtn, true);
    const res = await fetch('/api/update_media.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ media_id: _mediaId, order_id: _orderId, caption: input.value.trim() })
    }).then(r => r.json()).catch(() => ({ success: false, error: 'Request failed.' }));
    setLoading(saveBtn, false);
    if (!res.success) { errEl.textContent = res.error; errEl.style.display = ''; return; }
    modal.classList.remove('open');
    await refreshMedia();
  });

  cancelBtn.addEventListener('click', () => modal.classList.remove('open'));
  modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('open'); });
})();

// ── Delete Media Modal (global — works for both admin and customer) ─────────

(function setupDeleteMediaModal() {
  const modal      = document.getElementById('delete-media-modal');
  const confirmBtn = document.getElementById('delete-media-confirm');
  const cancelBtn  = document.getElementById('delete-media-cancel');
  if (!modal) return;

  let _mediaId = '', _orderId = '';

  window.openDeleteMediaModal = function(mediaId, orderId) {
    _mediaId = mediaId; _orderId = orderId;
    modal.classList.add('open');
  };

  confirmBtn.addEventListener('click', async () => {
    setLoading(confirmBtn, true);
    const res = await fetch('/api/delete_media.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ media_id: _mediaId, order_id: _orderId })
    }).then(r => r.json()).catch(() => ({ success: false, error: 'Request failed.' }));
    setLoading(confirmBtn, false);
    if (!res.success) { alert(res.error); return; }
    modal.classList.remove('open');
    await refreshMedia();
  });

  cancelBtn.addEventListener('click', () => modal.classList.remove('open'));
  modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('open'); });
})();

// ── Admin media grid drag-and-drop ─────────────────────────────────────────

function initAdminMediaGridDrop(orderId) {
  const grid = document.getElementById('admin-media-grid');
  if (!grid || grid.dataset.dropInited) return;
  grid.dataset.dropInited = '1';

  grid.addEventListener('dragover', e => {
    e.preventDefault();
    grid.classList.add('media-grid--dragging');
  });
  grid.addEventListener('dragleave', e => {
    if (!grid.contains(e.relatedTarget)) grid.classList.remove('media-grid--dragging');
  });
  grid.addEventListener('drop', async e => {
    e.preventDefault();
    grid.classList.remove('media-grid--dragging');
    const files = Array.from(e.dataTransfer.files);
    if (!files.length) return;
    const validTypes = ['image/jpeg','image/png','image/webp','image/gif','video/mp4','video/quicktime','video/x-msvideo','application/pdf'];
    const validExts2 = /\.(jpe?g|png|webp|gif|mp4|mov|avi|pdf)$/i;
    const isAllowed  = f => validTypes.includes(f.type) || validExts2.test(f.name);
    const invalid = files.filter(f => !isAllowed(f));
    if (invalid.length) { alert(`Skipping ${invalid.length} unsupported file(s). Use JPG, PNG, WEBP, GIF, MP4, MOV, or PDF.`); }
    const toUpload = files.filter(f => isAllowed(f) && f.size <= 100 * 1024 * 1024);
    const tooBig = files.filter(f => isAllowed(f) && f.size > 100 * 1024 * 1024);
    if (tooBig.length) alert(`Skipping ${tooBig.length} file(s) over 100MB.`);
    if (!toUpload.length) return;
    grid.classList.add('media-grid--uploading');
    const results = await Promise.all(toUpload.map(file => {
      const fd = new FormData();
      fd.append('file', file);
      fd.append('order_id', orderId);
      fd.append('caption', '');
      return fetch('/api/upload_media.php', { method: 'POST', credentials: 'same-origin', body: fd })
        .then(r => r.json()).catch(() => ({ success: false, error: 'Upload failed.' }));
    }));
    grid.classList.remove('media-grid--uploading');
    const failed = results.filter(r => !r.success);
    if (failed.length) alert(`${failed.length} file(s) failed to upload.`);
    await refreshMedia();
  });
}

// ── Render Portal ─────────────────────────────────────────────────────────

function initRenderPortal() {
  const btn = document.getElementById('btn-copy-render-pw');
  if (!btn) return;
  btn.addEventListener('click', () => {
    const pw = (document.getElementById('render-portal-password').innerText || '').trim();
    if (!pw) return;
    const done = () => {
      btn.textContent = 'Copied!';
      setTimeout(() => btn.textContent = 'Copy', 2000);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(pw).then(done).catch(() => fallbackCopy(pw, done));
    } else {
      fallbackCopy(pw, done);
    }
  });
}

function fallbackCopy(text, cb) {
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;';
  document.body.appendChild(ta);
  ta.select();
  try { document.execCommand('copy'); cb(); } catch(e) {}
  document.body.removeChild(ta);
}

// ── Admin ─────────────────────────────────────────────────────────────────

const Admin = (() => {
  let _allOrders = [];
  let _sortCol = 'updated_at';
  let _sortDir = -1; // -1 = desc, 1 = asc

  function initDashboard() {
    loadOrders();
    const btnNew = document.getElementById('btn-new-order');
    if (btnNew) btnNew.addEventListener('click', () => window.location.href = '/admin-order.php?new=1');

    // Search
    const searchEl = document.getElementById('orders-search');
    if (searchEl) searchEl.addEventListener('input', () => renderOrders(_allOrders));

    // Sort
    document.querySelectorAll('.sortable[data-col]').forEach(th => {
      th.addEventListener('click', () => {
        const col = th.dataset.col;
        if (_sortCol === col) { _sortDir *= -1; } else { _sortCol = col; _sortDir = -1; }
        renderOrders(_allOrders);
      });
    });
  }

  async function loadOrders() {
    const res = await apiFetch('/api/admin_orders.php');
    const tbody = document.getElementById('orders-tbody');
    if (!res.success || !res.orders || !res.orders.length) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--muted);padding:24px;">No orders yet.</td></tr>';
      return;
    }
    _allOrders = res.orders;
    renderOrders(_allOrders);
  }

  function renderOrders(orders) {
    const tbody   = document.getElementById('orders-tbody');
    const emptyEl = document.getElementById('orders-empty');
    const query   = (document.getElementById('orders-search')?.value || '').toLowerCase().trim();

    // Filter
    let filtered = query
      ? orders.filter(o =>
          (o.order_id || '').toLowerCase().includes(query) ||
          (o.customer_name || '').toLowerCase().includes(query) ||
          (o.email || '').toLowerCase().includes(query) ||
          (o.status || '').toLowerCase().includes(query))
      : orders;

    // Sort
    filtered = [...filtered].sort((a, b) => {
      let av = a[_sortCol] ?? '', bv = b[_sortCol] ?? '';
      if (_sortCol === 'total_estimate') { av = parseFloat(av) || 0; bv = parseFloat(bv) || 0; }
      if (av < bv) return -1 * _sortDir;
      if (av > bv) return  1 * _sortDir;
      return 0;
    });

    // Update sort arrows
    document.querySelectorAll('.sort-arrow').forEach(el => el.classList.remove('active'));
    const activeArrow = document.getElementById('sort-' + _sortCol);
    if (activeArrow) { activeArrow.textContent = _sortDir === -1 ? '↓' : '↑'; activeArrow.classList.add('active'); }

    if (!filtered.length) {
      tbody.innerHTML = '';
      if (emptyEl) emptyEl.style.display = '';
      return;
    }
    if (emptyEl) emptyEl.style.display = 'none';

    const statuses = [
      'Design Review','Design in Process','Awaiting Design Approval',
      '3D Printing Resin/Wax Model','Awaiting 3D Printed Resin Approval',
      'In Production','Casting','Setting Stones',
      'Complete and Awaiting Payment','Complete and Ready for Delivery','Sent Overnight Mail'
    ];

    tbody.innerHTML = filtered.map(o => {
      const hasNotif = o.ring_approved_notification || o.skip_resin_requested === '1' || o.facetime_requested === '1';
      const notifDot = hasNotif ? '<span class="notif-dot" title="Needs attention"></span>' : '';
      const statusClass = 'status-' + (o.status || '').replace(/[\s/]+/g, '-');
      const statusOpts = statuses.map(s =>
        `<option value="${escHtml(s)}" ${s === o.status ? 'selected' : ''}>${escHtml(s)}</option>`
      ).join('');
      return `
      <tr data-order-id="${escHtml(o.order_id)}">
        <td><strong>${escHtml(o.order_id)}</strong>${notifDot}</td>
        <td>${escHtml(o.customer_name)}<br><small style="color:var(--muted)">${escHtml(o.email)}</small></td>
        <td>
          <span class="status-badge ${escHtml(statusClass)}">
            <select class="inline-status-select" data-order-id="${escHtml(o.order_id)}" data-orig="${escHtml(o.status || '')}">${statusOpts}</select>
          </span>
        </td>
        <td>${o.total_estimate ? '$' + fmt(o.total_estimate) : '—'}</td>
        <td style="color:var(--muted);font-size:12px" title="${escHtml(o.updated_at || '')}">${relativeTime(o.updated_at)}</td>
        <td><a href="/admin-order.php?id=${encodeURIComponent(o.order_id)}" class="btn btn-sm btn-outline">Edit</a></td>
      </tr>`;
    }).join('');

    // Inline status change handlers
    tbody.querySelectorAll('.inline-status-select').forEach(sel => {
      // Keep badge color in sync as selection changes
      sel.addEventListener('change', async () => {
        const orderId = sel.dataset.orderId;
        const newStatus = sel.value;
        const badge = sel.closest('.status-badge');
        if (badge) {
          badge.className = 'status-badge status-' + newStatus.replace(/[\s/]+/g, '-');
        }
        const res = await apiPost('/api/admin_save_order.php', {
          mode: 'update', order_id: orderId, status: newStatus, suppress_email: true
        });
        if (res.success) {
          showToast('Status updated', 'success');
          sel.dataset.orig = newStatus;
          // Update local data
          const order = _allOrders.find(o => o.order_id === orderId);
          if (order) order.status = newStatus;
        } else {
          showToast('Failed to update status', 'error');
          sel.value = sel.dataset.orig;
        }
      });
      // Stop click from navigating to edit
      sel.addEventListener('click', e => e.stopPropagation());
    });
  }

  function relativeTime(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr.replace(' ', 'T'));
    if (isNaN(d)) return dateStr.substring(0, 10);
    const now = new Date();
    const diff = now - d;
    const mins  = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days  = Math.floor(diff / 86400000);
    if (mins < 2)   return 'Just now';
    if (mins < 60)  return `${mins}m ago`;
    if (hours < 24) {
      const t = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
      return `Today ${t}`;
    }
    if (days === 1) {
      const t = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
      return `Yesterday ${t}`;
    }
    if (days < 7)   return `${days}d ago`;
    return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
  }

  function initVersionsEditor(versionsJson, approvedVersionId) {
    let versions = [];
    try { versions = JSON.parse(versionsJson || '[]'); } catch(e) {}

    const editor = document.getElementById('versions-editor');
    const hiddenInput = document.getElementById('versions_json');
    if (!editor || !hiddenInput) return;

    function serializeVersions() {
      const inputs = editor.querySelectorAll('.version-label-input');
      const result = Array.from(inputs).map((inp, i) => ({
        id: inp.dataset.id || ('v' + (i + 1)),
        label: inp.value.trim()
      })).filter(v => v.label);
      hiddenInput.value = JSON.stringify(result);
    }

    function addVersionRow(version) {
      const row = document.createElement('div');
      row.className = 'version-editor-row';
      const id = version?.id || ('v' + (editor.querySelectorAll('.version-editor-row').length + 1));
      const isApproved = approvedVersionId && version?.id === approvedVersionId;
      row.innerHTML = `
        <input type="text" class="form-control version-label-input" data-id="${escHtml(id)}"
               value="${escHtml(version?.label || '')}" placeholder="e.g. Version 1 — Wider band" style="flex:1;">
        ${isApproved ? `<span class="version-approved-badge">✓ Customer Approved</span>` : ''}
        <button type="button" class="btn btn-outline btn-sm version-remove-btn">Remove</button>`;
      row.querySelector('.version-remove-btn').addEventListener('click', () => {
        row.remove();
        serializeVersions();
      });
      row.querySelector('.version-label-input').addEventListener('input', serializeVersions);
      editor.appendChild(row);
      serializeVersions();
    }

    versions.forEach(v => addVersionRow(v));

    const addBtn = document.getElementById('btn-add-version');
    if (addBtn) addBtn.addEventListener('click', () => addVersionRow(null));
  }

  let _hasUnsavedChanges = false;

  function markUnsaved() {
    _hasUnsavedChanges = true;
    const dot = document.getElementById('unsaved-dot');
    if (dot) dot.style.display = '';
  }

  function markSaved() {
    _hasUnsavedChanges = false;
    const dot = document.getElementById('unsaved-dot');
    if (dot) dot.style.display = 'none';
  }

  async function initEditor(opts) {
    const adminRole = (opts && opts.adminRole) || 'full';
    const isLimited = adminRole === 'limited';

    const params  = new URLSearchParams(window.location.search);
    const orderId = params.get('id');
    const isNew   = params.get('new') === '1';

    if (isNew) initVersionsEditor('[]', '');

    if (!isNew && orderId) {
      await loadOrderIntoForm(orderId);
    }

    // Sticky bar label
    const stickyLabel = document.getElementById('sticky-order-label');
    if (stickyLabel && orderId) stickyLabel.textContent = orderId;

    const form = document.getElementById('order-form');
    form.addEventListener('submit', handleSave);
    if (!isLimited) initEmailModeToggle();

    // Track unsaved changes
    form.addEventListener('input',  markUnsaved);
    form.addEventListener('change', markUnsaved);

    // Unsaved changes warning on navigate away
    window.addEventListener('beforeunload', e => {
      if (_hasUnsavedChanges) {
        e.preventDefault();
        e.returnValue = '';
      }
    });

    // Cmd+S / Ctrl+S to save
    document.addEventListener('keydown', e => {
      if ((e.metaKey || e.ctrlKey) && e.key === 's') {
        e.preventDefault();
        form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
      }
    });

    // Live payment formula preview
    const totalEl   = document.getElementById('total_estimate');
    const progEl    = document.getElementById('progress_deposit_due');
    const depositEl = document.getElementById('deposit_paid');
    const statusEl  = document.getElementById('status');
    if (totalEl)   totalEl.addEventListener('input',   () => updateFormula(true));
    if (depositEl) depositEl.addEventListener('input', () => updateFormula(true));
    if (progEl)    progEl.addEventListener('input',    () => { progEl.dataset.manuallySet = '1'; updateFormula(); });
    if (statusEl)  statusEl.addEventListener('change', () => toggleTrackingField(statusEl.value));
    updateFormula();
  }

  async function loadOrderIntoForm(orderId) {
    const res = await apiFetch('/api/admin_orders.php');
    if (!res.success) return;
    const order = res.orders.find(o => o.order_id === orderId);
    if (!order) { showError('Order not found.'); return; }

    document.getElementById('page-title').textContent = 'Edit Order — ' + order.order_id;
    document.getElementById('mode').value       = 'update';
    document.getElementById('order_id').value   = order.order_id;

    const phoneEl = document.getElementById('phone');
    if (phoneEl) {
      phoneEl.addEventListener('input', () => {
        const digits = phoneEl.value.replace(/\D/g, '').slice(0, 10);
        let formatted = digits;
        if (digits.length > 6)      formatted = digits.slice(0,3) + '-' + digits.slice(3,6) + '-' + digits.slice(6);
        else if (digits.length > 3) formatted = digits.slice(0,3) + '-' + digits.slice(3);
        phoneEl.value = formatted;
      });
    }

    [
      'customer_name','email','phone','address',
      'status','timeline_note','estimated_completion','project_update',
      'total_estimate','deposit_paid','progress_deposit_due',
      'final_payment_due','amount_paid_total','shipping_charge','tracking_number'
    ].forEach(id => {
      const el = document.getElementById(id);
      if (el && order[id] !== undefined) {
        if (id === 'phone') {
          const digits = String(order[id]).replace(/\D/g, '').slice(0, 10);
          let formatted = digits;
          if (digits.length > 6)      formatted = digits.slice(0,3) + '-' + digits.slice(3,6) + '-' + digits.slice(6);
          else if (digits.length > 3) formatted = digits.slice(0,3) + '-' + digits.slice(3);
          el.value = formatted;
        } else {
          el.value = order[id];
        }
        if (id === 'progress_deposit_due' && order[id] !== '' && order[id] !== null && parseFloat(order[id]) > 0) {
          el.dataset.manuallySet = '1';
        }
        if (id === 'final_payment_due' && order[id] !== '' && order[id] !== null && parseFloat(order[id]) > 0) {
          el.dataset.manuallySet = '1';
        }
      }
    });

    // Final payment auto-calc
    const totalEl     = document.getElementById('total_estimate');
    const paidEl      = document.getElementById('amount_paid_total');
    const finalDueEl  = document.getElementById('final_payment_due');
    const finalPreview = document.getElementById('final-formula-preview');
    const enableFinalCb = document.getElementById('final_payment_enabled');

    function calcFinalDue() {
      const total = parseFloat(totalEl?.value) || 0;
      const paid  = parseFloat(paidEl?.value)  || 0;
      return Math.max(0, total - paid);
    }

    function updateFinalPreview() {
      if (!finalPreview) return;
      const total = parseFloat(totalEl?.value) || 0;
      const paid  = parseFloat(paidEl?.value)  || 0;
      if (total > 0) {
        const auto = Math.max(0, total - paid);
        finalPreview.textContent = `Auto: $${total.toLocaleString()} total − $${paid.toLocaleString()} paid = $${auto.toLocaleString()}`;
      } else {
        finalPreview.textContent = 'Enter a total estimate to see the auto-calculated amount.';
      }
    }

    function applyAutoFinalDue() {
      if (finalDueEl && !finalDueEl.dataset.manuallySet) {
        finalDueEl.value = calcFinalDue();
      }
    }

    // Mark final_payment_due as manually set when admin types in it
    if (finalDueEl) {
      finalDueEl.addEventListener('input', () => {
        finalDueEl.dataset.manuallySet = '1';
      });
    }

    // When total or paid changes, update preview and recalc if not manually set
    [totalEl, paidEl].forEach(el => {
      if (el) el.addEventListener('input', () => {
        updateFinalPreview();
        applyAutoFinalDue();
      });
    });

    // When "Enable Pay Final Balance" is checked, push auto-calc value if not manually set
    if (enableFinalCb) {
      enableFinalCb.addEventListener('change', () => {
        if (enableFinalCb.checked && finalDueEl && !finalDueEl.dataset.manuallySet) {
          finalDueEl.value = calcFinalDue();
        }
      });
    }

    updateFinalPreview();
    applyAutoFinalDue();

    toggleTrackingField(order.status);

    // Skip resin banner
    const skipField   = document.getElementById('skip_resin_requested');
    const skipBanner  = document.getElementById('skip-resin-banner');
    const skipDismiss = document.getElementById('skip-resin-dismiss');
    if (skipField) skipField.value = order.skip_resin_requested || '';
    if (skipBanner) {
      skipBanner.style.display = order.skip_resin_requested === '1' ? 'flex' : 'none';
    }
    if (skipDismiss && !skipDismiss.dataset.listenerAttached) {
      skipDismiss.dataset.listenerAttached = '1';
      skipDismiss.addEventListener('click', async () => {
        if (skipField) skipField.value = '0';
        if (skipBanner) skipBanner.style.display = 'none';
        // Persist immediately so it doesn't reappear on reload
        const orderId = document.getElementById('order_id')?.value || '';
        if (orderId) {
          await apiPost('/api/admin_save_order.php', { mode: 'update', order_id: orderId, skip_resin_requested: '0' });
        }
      });
    }

    // Ring approved notification banner
    const approvedNotifField   = document.getElementById('ring_approved_notification');
    const approvedBanner       = document.getElementById('ring-approved-banner');
    const approvedBannerLabel  = document.getElementById('ring-approved-banner-label');
    const approvedDismiss      = document.getElementById('ring-approved-dismiss');
    const approvedNotifVal     = order.ring_approved_notification || '';
    if (approvedNotifField) approvedNotifField.value = approvedNotifVal;
    if (approvedBanner) {
      approvedBanner.style.display = approvedNotifVal ? 'flex' : 'none';
      if (approvedBannerLabel) approvedBannerLabel.textContent = `Approved version: "${approvedNotifVal}"`;
    }
    if (approvedDismiss && !approvedDismiss.dataset.listenerAttached) {
      approvedDismiss.dataset.listenerAttached = '1';
      approvedDismiss.addEventListener('click', async () => {
        if (approvedNotifField) approvedNotifField.value = '';
        if (approvedBanner) approvedBanner.style.display = 'none';

        // Persist immediately so it doesn't reappear on reload
        const orderId = document.getElementById('order_id')?.value || '';
        if (orderId) {
          await apiPost('/api/admin_save_order.php', { mode: 'update', order_id: orderId, ring_approved_notification: '' });
        }

        // Add approved badge to the matching version row in the editor
        const approvedId = document.getElementById('approved_version_id')?.value || '';
        if (approvedId) {
          document.querySelectorAll('.version-label-input').forEach(inp => {
            const row = inp.closest('.version-editor-row');
            if (!row) return;
            if (inp.dataset.id === approvedId && !row.querySelector('.version-approved-badge')) {
              const badge = document.createElement('span');
              badge.className = 'version-approved-badge';
              badge.textContent = '✓ Customer Approved';
              inp.insertAdjacentElement('afterend', badge);
            }
          });
        }
      });
    }

    // FaceTime banner
    const facetimeField  = document.getElementById('facetime_requested');
    const facetimeBanner = document.getElementById('facetime-banner');
    if (facetimeField) facetimeField.value = order.facetime_requested || '';
    if (facetimeBanner) {
      facetimeBanner.style.display = order.facetime_requested === '1' ? 'flex' : 'none';
    }

    // Care plan banner
    const carePlanField      = document.getElementById('care_plan_purchased');
    const carePlanAmtField   = document.getElementById('care_plan_amount');
    const carePlanAdminBanner = document.getElementById('care-plan-banner');
    const carePlanBannerAmt  = document.getElementById('care-plan-banner-amount');
    if (carePlanField)  carePlanField.value  = order.care_plan_purchased || '';
    if (carePlanAmtField) carePlanAmtField.value = order.care_plan_amount || '';
    if (carePlanAdminBanner) {
      carePlanAdminBanner.style.display = order.care_plan_purchased === '1' ? 'flex' : 'none';
      if (carePlanBannerAmt) {
        carePlanBannerAmt.textContent = order.care_plan_amount ? '$' + parseFloat(order.care_plan_amount).toFixed(2) : '';
      }
    }

    const fpEnabled = document.getElementById('final_payment_enabled');
    if (fpEnabled) fpEnabled.checked = order.final_payment_enabled;


    // Populate ring choices JSON textarea
    const rcEl = document.getElementById('ring_choices_json');
    if (rcEl) rcEl.value = order.ring_choices_json || '[]';

    // Populate versions editor
    const approvedVersionId = order.approved_version_id || '';
    initVersionsEditor(order.versions_json || '[]', approvedVersionId);

    // Approval reset button
    const approvedField = document.getElementById('approved_version_id');
    const resetBtn      = document.getElementById('btn-reset-approval');
    if (approvedField) approvedField.value = approvedVersionId;
    if (resetBtn) {
      resetBtn.style.display = approvedVersionId ? '' : 'none';
      resetBtn.addEventListener('click', () => {
        if (!confirm('Reset the customer approval? The customer will be able to approve a version again.')) return;
        if (approvedField) approvedField.value = '';
        resetBtn.style.display = 'none';
        // Remove approved badges from all version rows
        document.querySelectorAll('.version-approved-badge').forEach(b => b.remove());
      });
    }

    // Show media section
    if (order.order_id) {
      renderAdminMedia(order.order_id);
    }

    // Send Estimate section
    initEstimateEditor(order);

    updateFormula();

    // Mark clean after loading so initial load doesn't trigger unsaved dot
    setTimeout(markSaved, 100);
  }

  let _adminMediaOrderId = '';

  async function renderAdminMedia(orderId) {
    _adminMediaOrderId = orderId;
    const res = await apiFetch('/api/admin_media.php?order_id=' + encodeURIComponent(orderId));
    const grid = document.getElementById('admin-media-grid');
    if (!grid) return;

    grid.innerHTML = '';

    if (!res.success || !res.media || !res.media.length) {
      grid.innerHTML = '<div class="media-empty" style="grid-column:1/-1">No media yet.</div>';
    } else {
      res.media.forEach(m => {
        const wrapper = document.createElement('div');
        wrapper.className = 'admin-media-wrapper';

        const item = document.createElement('div');
        item.className = 'media-item';

        const isVideo = m.filename && m.filename.match(/\.(mp4|mov|avi)$/i);
        const isPdf   = m.filename && m.filename.match(/\.pdf$/i);
        const captionHtml = m.caption
          ? `<span class="media-caption-label">${escHtml(m.caption)}</span>`
          : '';
        if (isPdf) {
          item.innerHTML = `
            <a href="${escHtml(m.thumbnail_url)}" target="_blank" rel="noopener" style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;text-decoration:none;color:inherit;">
              <div style="font-size:36px;margin-bottom:6px;">📄</div>
              <div style="font-size:11px;color:var(--muted);text-align:center;padding:0 6px;word-break:break-all;">${escHtml(m.caption || m.filename)}</div>
            </a>
            <span class="media-badge">${escHtml(m.uploader)}</span>
            <button class="media-delete-btn" title="Remove">✕</button>
            <button class="media-edit-btn" title="Edit caption">✎</button>`;
        } else if (isVideo) {
          item.innerHTML = `
            <img src="${escHtml(m.thumbnail_url)}" alt="${escHtml(m.caption || m.filename)}" loading="lazy">
            <div class="media-play">▶</div>
            <span class="media-badge">${escHtml(m.uploader)}</span>
            ${captionHtml}
            <button class="media-delete-btn" title="Remove">✕</button>
            <button class="media-edit-btn" title="Edit caption">✎</button>`;
          item.querySelector('img').addEventListener('click', () => openLightboxImage(m.thumbnail_url, m.caption));
        } else {
          item.innerHTML = `
            <img src="${escHtml(m.thumbnail_url)}" alt="${escHtml(m.caption || m.filename)}" loading="lazy">
            <span class="media-badge">${escHtml(m.uploader)}</span>
            ${captionHtml}
            <button class="media-delete-btn" title="Remove">✕</button>
            <button class="media-edit-btn" title="Edit caption">✎</button>`;
          item.querySelector('img').addEventListener('click', () => openLightboxImage(m.thumbnail_url, m.caption));
        }

        item.querySelector('.media-delete-btn').addEventListener('click', (e) => {
          e.stopPropagation();
          openDeleteMediaModal(m.media_id, orderId);
        });
        item.querySelector('.media-edit-btn').addEventListener('click', (e) => {
          e.stopPropagation();
          openEditCaptionModal(m.media_id, orderId, m.caption || '');
        });

        wrapper.appendChild(item);

        const captionText = document.createElement('div');
        captionText.className = 'admin-media-caption';
        captionText.textContent = m.caption || '';
        wrapper.appendChild(captionText);

        grid.appendChild(wrapper);
      });
    }

    const uploadBtn = document.createElement('div');
    uploadBtn.className = 'media-upload-btn';
    uploadBtn.innerHTML = '+';
    uploadBtn.addEventListener('click', () => openUploadModal(orderId));
    grid.appendChild(uploadBtn);
    initUpload();
    initLightbox();
    initAdminMediaGridDrop(orderId);
  }

  async function refreshAdminMedia() {
    if (_adminMediaOrderId) await renderAdminMedia(_adminMediaOrderId);
  }

  function initEstimateEditor(order) {
    const card      = document.getElementById('estimate-card');
    const linesWrap = document.getElementById('estimate-lines');
    const addBtn    = document.getElementById('btn-add-estimate-line');
    const sendBtn   = document.getElementById('btn-send-estimate');
    const totalDisp = document.getElementById('estimate-total-display');
    const msgEl     = document.getElementById('estimate-msg');
    const sentBadge = document.getElementById('estimate-sent-badge');
    if (!card) return;

    card.style.display = '';

    // Load existing lines if any
    let existingLines = [];
    try { existingLines = JSON.parse(order.estimate_json || '[]'); } catch(e) {}

    // If no lines yet, seed with defaults
    if (!existingLines.length) {
      existingLines = [
        { label: 'Base Ring', amount: '' },
        { label: 'Center Stone', amount: '' },
      ];
    }

    existingLines.forEach(l => addLine(l.label, l.amount));
    updateTotal();

    addBtn.addEventListener('click', () => { addLine('', ''); updateTotal(); });
    if (sentBadge) sentBadge.style.display = order.estimate_json ? '' : 'none';

    sendBtn.addEventListener('click', async () => {
      const lines = collectLines();
      if (!lines.length) { showEstimateMsg('Add at least one line item.', 'error'); return; }
      for (const l of lines) {
        if (!l.label.trim()) { showEstimateMsg('All line items need a label.', 'error'); return; }
        if (isNaN(parseFloat(l.amount)) || parseFloat(l.amount) < 0) {
          showEstimateMsg('All line items need a valid dollar amount.', 'error'); return;
        }
      }

      const orderId = document.getElementById('order_id')?.value || '';
      if (!orderId) { showEstimateMsg('Save the order first.', 'error'); return; }

      if (!confirm('Send the estimate email to the customer?')) return;

      setLoading(sendBtn, true);
      showEstimateMsg('', '');
      const res = await apiPost('/api/send_estimate.php', { order_id: orderId, lines });
      setLoading(sendBtn, false);

      if (res.success) {
        showEstimateMsg('Estimate sent! The total estimate field has been updated.', 'success');
        if (sentBadge) sentBadge.style.display = '';
        // Update total_estimate field in form to reflect
        const totalEl = document.getElementById('total_estimate');
        if (totalEl) { totalEl.value = res.total; updateFormula(true); }
      } else {
        showEstimateMsg(res.error || 'Failed to send.', 'error');
      }
    });

    function addLine(label, amount) {
      const row = document.createElement('div');
      row.className = 'estimate-line-row';
      row.style.cssText = 'display:flex;gap:8px;align-items:center;';
      row.innerHTML = `
        <input type="text" class="form-control estimate-line-label" placeholder="Line item (e.g. Base Ring)" value="${escHtml(String(label || ''))}" style="flex:2;">
        <div style="position:relative;flex:1;">
          <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#6b7280;font-size:14px;">$</span>
          <input type="number" class="form-control estimate-line-amount" placeholder="0" value="${escHtml(String(amount || ''))}" min="0" step="1" style="padding-left:22px;">
        </div>
        <button type="button" class="btn btn-outline btn-sm estimate-line-remove" style="padding:6px 10px;color:#e53e3e;border-color:#e53e3e;flex-shrink:0;" title="Remove">✕</button>`;
      row.querySelector('.estimate-line-amount').addEventListener('input', updateTotal);
      row.querySelector('.estimate-line-remove').addEventListener('click', () => { row.remove(); updateTotal(); });
      linesWrap.appendChild(row);
    }

    function collectLines() {
      const rows = linesWrap.querySelectorAll('.estimate-line-row');
      return Array.from(rows).map(r => ({
        label:  r.querySelector('.estimate-line-label').value.trim(),
        amount: r.querySelector('.estimate-line-amount').value,
      }));
    }

    function updateTotal() {
      const lines = collectLines();
      const total = lines.reduce((s, l) => s + (parseFloat(l.amount) || 0), 0);
      if (totalDisp) totalDisp.textContent = '$' + total.toLocaleString();
    }

    function showEstimateMsg(text, type) {
      if (!msgEl) return;
      msgEl.textContent = text;
      msgEl.style.display = text ? '' : 'none';
      msgEl.className = type === 'error' ? 'error-msg' : 'success-msg';
    }
  }

  function toggleTrackingField(status) {
    const wrap = document.getElementById('tracking-field');
    if (wrap) wrap.style.display = (status === 'Sent Overnight Mail') ? '' : 'none';
  }

  return { initDashboard, initEditor, refreshAdminMedia };

  function initEmailModeToggle() {
    const radios = document.querySelectorAll('input[name=email_mode]');
    const hint   = document.getElementById('manual-email-hint');
    if (!radios.length || !hint) return;

    const params  = new URLSearchParams(window.location.search);
    const orderId = params.get('id') || 'new';
    const storageKey = 'emailMode_' + orderId;

    // Restore saved preference
    const saved = localStorage.getItem(storageKey);
    if (saved) {
      radios.forEach(r => { r.checked = (r.value === saved); });
      hint.style.display = saved === 'manual' ? '' : 'none';
    }

    radios.forEach(r => r.addEventListener('change', () => {
      localStorage.setItem(storageKey, r.value);
      hint.style.display = r.value === 'manual' && r.checked ? '' : 'none';
    }));
  }

  async function handleSave(e) {
    e.preventDefault();
    const btn   = e.target.querySelector('button[type=submit]');
    // Serialize versions before reading form data
    const vInputs = document.querySelectorAll('.version-label-input');
    if (vInputs.length > 0) {
      const versions = Array.from(vInputs).map((inp, i) => ({
        id: inp.dataset.id || ('v' + (i + 1)),
        label: inp.value.trim()
      })).filter(v => v.label);
      const vHidden = document.getElementById('versions_json');
      if (vHidden) vHidden.value = JSON.stringify(versions);
    }
    const data  = Object.fromEntries(new FormData(e.target).entries());
    data.final_payment_enabled = document.getElementById('final_payment_enabled').checked;
    // Coerce numerics
    ['total_estimate','deposit_paid','progress_deposit_due','final_payment_due','amount_paid_total','shipping_charge']
      .forEach(k => { if (data[k] !== undefined) data[k] = parseFloat(data[k]) || 0; });

    // Email mode toggle
    const emailModeEl = document.querySelector('input[name=email_mode]:checked');
    const suppressEmail = emailModeEl?.value === 'manual';
    if (suppressEmail) data.suppress_email = true;

    setLoading(btn, true);
    clearMsg();
    const res = await apiPost('/api/admin_save_order.php', data);
    setLoading(btn, false);

    if (res.success) {
      markSaved();
      if (suppressEmail && data.mode !== 'create') {
        // Show manual mailto helper
        const cta      = document.getElementById('manual-email-cta');
        const mailtoEl = document.getElementById('manual-mailto-link');
        if (cta && mailtoEl) {
          const customerEmail = document.getElementById('email')?.value || '';
          const customerName  = document.getElementById('customer_name')?.value || '';
          const newStatus     = document.getElementById('status')?.value || '';
          const subject = encodeURIComponent('Your Ring Project Update — The Right Ring');
          const body    = encodeURIComponent(`Hi ${customerName.split(' ')[0]},\n\nI wanted to personally reach out about your ring project. ${newStatus ? 'Your project is now at: ' + newStatus + '.' : ''}\n\nTalk soon,\nMatt\nThe Right Ring\ndesign@therightring.com`);
          mailtoEl.href = `mailto:${customerEmail}?subject=${subject}&body=${body}`;
          cta.style.display = '';
          cta.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        showToast('Saved — email not sent', 'success');
      } else {
        showToast(data.mode === 'create' ? 'Order created! ID: ' + res.order_id : 'Saved successfully', 'success');
      }
      if (data.mode === 'create' && res.order_id) {
        setTimeout(() => window.location.href = '/admin-order.php?id=' + res.order_id, 1200);
      }
    } else {
      showToast(res.error || 'Save failed', 'error');
      showError(res.error || 'Save failed.');
    }
  }

  function updateFormula(autoFill = false) {
    const total       = parseFloat(document.getElementById('total_estimate')?.value) || 0;
    const deposit     = parseFloat(document.getElementById('deposit_paid')?.value) || 0;
    const progField   = document.getElementById('progress_deposit_due');
    const calculated  = total > 0 ? Math.max(0, total / 2 - deposit) : 0;

    if (autoFill && progField && !progField.dataset.manuallySet) {
      progField.value = total > 0 ? calculated : '';
    }

    const override = parseFloat(progField?.value);
    const progDue  = isNaN(override) ? calculated : override;
    const el       = document.getElementById('formula-preview');
    if (el && total > 0) {
      el.textContent = `50% of $${fmt(total)} = $${fmt(total/2)} — $${fmt(deposit)} deposit = $${fmt(progDue)} progress deposit`;
    }
  }

})();

// ── Shared utilities ──────────────────────────────────────────────────────

async function apiFetch(url) {
  try {
    const r = await fetch(url, { credentials: 'same-origin' });
    if (!r.ok) return { success: false, error: 'Server error (' + r.status + ').' };
    return await r.json();
  } catch(e) {
    return { success: false, error: 'Network error.' };
  }
}

async function apiPost(url, data) {
  try {
    const r = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    });
    if (!r.ok) return { success: false, error: 'Server error (' + r.status + ').' };
    return await r.json();
  } catch(e) {
    return { success: false, error: 'Network error.' };
  }
}

function setLoading(btn, loading) {
  if (!btn) return;
  if (loading) {
    btn.dataset.origText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner"></span>';
    btn.disabled = true;
  } else {
    btn.innerHTML = btn.dataset.origText || btn.innerHTML;
    btn.disabled = false;
  }
}

function showError(msg) {
  const el = document.getElementById('msg-error');
  if (el) { el.textContent = msg; el.style.display = ''; }
}

function showSuccess(msg) {
  const el = document.getElementById('msg-success');
  if (el) { el.textContent = msg; el.style.display = ''; }
}

function clearMsg() {
  ['msg-error','msg-success'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
  });
}

function showToast(msg, type = 'success') {
  const existing = document.querySelector('.toast');
  if (existing) existing.remove();
  const el = document.createElement('div');
  el.className = `toast toast-${type}`;
  el.innerHTML = `<span>${escHtml(msg)}</span><button class="toast-dismiss" title="Dismiss">✕</button>`;
  el.querySelector('.toast-dismiss').addEventListener('click', () => el.remove());
  document.body.appendChild(el);
  setTimeout(() => { if (el.parentNode) el.remove(); }, 4000);
}

function copyFieldValue(fieldId, btn) {
  const el = document.getElementById(fieldId);
  if (!el) return;
  const val = el.value.trim();
  if (!val) return;
  const done = () => {
    const orig = btn.textContent;
    btn.textContent = 'Copied!';
    setTimeout(() => btn.textContent = orig, 2000);
  };
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(val).then(done).catch(() => fallbackCopy(val, done));
  } else {
    fallbackCopy(val, done);
  }
}

function showBanner(msg, type) {
  const el = document.createElement('div');
  el.className = type === 'success' ? 'success-msg' : 'error-msg';
  el.textContent = msg;
  el.style.cssText = 'position:fixed;top:80px;left:50%;transform:translateX(-50%);z-index:200;padding:14px 22px;border-radius:10px;max-width:440px;width:calc(100%-32px);box-shadow:0 4px 16px rgba(0,0,0,0.12);';
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 5000);
}

function escHtml(str) {
  return String(str || '')
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;')
    .replace(/'/g,'&#39;');
}

function fmt(n) {
  return Number(n).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}
function fmtMoney(n) {
  return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(str) {
  if (!str) return 'TBD';
  try {
    // Parse as local date to avoid UTC-to-local timezone shift
    const parts = str.split(/[-T]/);
    const d = new Date(+parts[0], +parts[1] - 1, +parts[2]);
    return d.toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
  } catch(e) { return str; }
}
