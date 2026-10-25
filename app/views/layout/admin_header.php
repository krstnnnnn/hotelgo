<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); $isAdmin = $_user && $_user['role'] === 'admin'; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($_title) ?> · HOTELGO Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
</head>
<body>
<div class="admin">
<aside class="sidebar" aria-label="Admin menu">
  <a class="logo" href="<?= e(site_url('admin')) ?>"><span>HOTEL<b>GO</b></span></a>
  <a class="<?= nav_active('admin') ?>" href="<?= e(site_url('admin')) ?>">Dashboard</a>
  <a class="<?= nav_active('admin/reservations') ?>" href="<?= e(site_url('admin/reservations')) ?>">Reservations</a>
  <a class="<?= nav_active('admin/walkin') ?>" href="<?= e(site_url('admin/walkin')) ?>">Walk-in booking</a>
  <a class="<?= nav_active('admin/guests') ?>" href="<?= e(site_url('admin/guests')) ?>">Guest records</a>
  <?php if ($isAdmin): ?>
    <div class="sec">Owner only</div>
    <a class="<?= nav_active('admin/rooms') ?>" href="<?= e(site_url('admin/rooms')) ?>">Rooms &amp; rates</a>
    <a class="<?= nav_active('admin/payments') ?>" href="<?= e(site_url('admin/payments')) ?>">Payments</a>
    <a class="<?= nav_active('admin/notifications') ?>" href="<?= e(site_url('admin/notifications')) ?>">Notifications</a>
    <a class="<?= nav_active('admin/reports') ?>" href="<?= e(site_url('admin/reports')) ?>">Reports</a>
    <a class="<?= nav_active('admin/staff') ?>" href="<?= e(site_url('admin/staff')) ?>">Staff &amp; roles</a>
    <a class="<?= nav_active('admin/audit') ?>" href="<?= e(site_url('admin/audit')) ?>">Audit log</a>
    <a class="<?= nav_active('admin/settings') ?>" href="<?= e(site_url('admin/settings')) ?>">Settings</a>
  <?php endif; ?>
  <div class="sec">New</div>
  <?php if (config_item('hotelgo_frontend_url') !== ''): ?><a href="<?= e(config_item('hotelgo_frontend_url')) ?>" target="_blank" rel="noopener">React console ↗</a><?php endif; ?>
  <div class="me"><b><?= e($_user['full_name']) ?></b><span class="mute"><?= $isAdmin ? 'Admin · Owner' : 'Front desk' ?></span>
    <div class="row" style="margin-top:10px"><a href="<?= e(site_url('')) ?>" style="padding:0;color:#fff">Guest site ↗</a>
    <a href="<?= e(site_url('account/password')) ?>" style="padding:0;color:#fff">Password</a></div>
    <form method="post" action="<?= e(site_url('logout')) ?>" style="margin-top:10px"><?= csrf_input() ?><button class="btn btn-sm btn-block btn-out" type="submit">Sign out</button></form></div>
</aside>
<div class="main">
<?php foreach ($_flash as [$t, $m]): ?><div class="flash <?= e($t) ?>" role="status"><?= e($m) ?></div><?php endforeach; ?>
