<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/bootstrap.php';

requireAdminLogin();
$isFullAdmin = isFullAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Orders — The Right Ring Portal</title>
  <link rel="icon" type="image/png" href="https://framerusercontent.com/images/FHftFuIChaavuwoII685yqNf6A.png">
  <link rel="stylesheet" href="/assets/portal.css">
</head>
<body>

<header class="admin-header">
  <div style="display:flex;align-items:center;gap:12px;">
    <img class="logo" src="https://framerusercontent.com/images/FHftFuIChaavuwoII685yqNf6A.png" alt="The Right Ring">
    <h1><?= $isFullAdmin ? "Matt's Admin Portal" : "Kiana's Admin Portal" ?></h1>
  </div>
  <div style="display:flex;gap:10px;align-items:center;">
    <?php if ($isFullAdmin): ?><button class="btn btn-primary btn-sm" id="btn-new-order">+ New Order</button><?php endif; ?>
    <a href="/logout.php" class="btn btn-outline btn-sm" style="color:#fff;border-color:rgba(255,255,255,0.3);">Log Out</a>
  </div>
</header>

<main class="admin-main">
  <div class="dashboard-toolbar">
    <h2 style="font-size:20px;white-space:nowrap;">All Orders</h2>
    <input type="search" id="orders-search" class="dashboard-search" placeholder="Search by name, email, order ID…">
  </div>

  <div style="overflow-x:auto;">
    <table class="orders-table">
      <thead>
        <tr>
          <th class="sortable" data-col="order_id">Order ID <span class="sort-arrow" id="sort-order_id">↕</span></th>
          <th class="sortable" data-col="customer_name">Customer <span class="sort-arrow" id="sort-customer_name">↕</span></th>
          <th class="sortable" data-col="status">Status <span class="sort-arrow" id="sort-status">↕</span></th>
          <th class="sortable" data-col="total_estimate">Total Estimate <span class="sort-arrow" id="sort-total_estimate">↕</span></th>
          <th class="sortable" data-col="updated_at">Last Updated <span class="sort-arrow" id="sort-updated_at">↕</span></th>
          <th></th>
        </tr>
      </thead>
      <tbody id="orders-tbody">
        <tr>
          <td colspan="6" style="text-align:center;color:var(--muted);padding:40px;">
            <div class="spinner" style="width:24px;height:24px;border-width:2px;"></div>
          </td>
        </tr>
      </tbody>
    </table>
    <div id="orders-empty" style="display:none;text-align:center;color:var(--muted);padding:32px;font-size:14px;">No orders match your search.</div>
  </div>
</main>

<script src="/assets/portal.js?v=<?= filemtime(__DIR__ . '/assets/portal.js') ?>"></script>
<script>Admin.initDashboard();</script>
</body>
</html>
