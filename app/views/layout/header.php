<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($_title) ?> · HOTELGO</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600;1,700&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
</head>
<body class="<?= !empty($landing) ? 'is-landing' : '' ?>">
<header class="nav" id="nav"><div class="container nav-in">
  <a class="logo" href="<?= e(site_url('')) ?>"><i><svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s7-6.5 7-12a7 7 0 10-14 0c0 5.5 7 12 7 12z" fill="#fff"/><circle cx="12" cy="10" r="3" fill="#0E7C86"/></svg></i><span>HOTEL<b>GO</b></span></a>
  <button class="burger" type="button" aria-label="Menu" aria-expanded="false" aria-controls="navlinks"><span></span><span></span><span></span></button>
  <nav class="nav-links" id="navlinks" aria-label="Main">
    <?php $h = site_url(''); ?>
    <a class="link<?= nav_active('rooms') ?>" href="<?= e(site_url('rooms')) ?>">Rooms</a>
    <a class="link" href="<?= e($h) ?>#offer">What we offer</a>
    <a class="link" href="<?= e($h) ?>#map">Map</a>
    <a class="link" href="<?= e($h) ?>#gallery">Gallery</a>
    <a class="link" href="<?= e($h) ?>#help">Help</a>
    <a class="link" href="<?= e($h) ?>#contact">Contact</a>
    <?php if ($_user): ?><a class="link<?= nav_active('my-bookings') ?>" href="<?= e(site_url('my-bookings')) ?>">My bookings</a><?php endif; ?>
    <?php if ($_user && in_array($_user['role'], ['staff', 'admin'], true)): ?><a class="link" href="<?= e(site_url('admin')) ?>">Admin area</a><?php endif; ?>
  </nav>
  <div class="row">
    <?php if ($_user): ?>
      <span class="small mute"><?= e($_user['full_name']) ?></span>
      <form class="inline" method="post" action="<?= e(site_url('logout')) ?>"><?= csrf_input() ?><button class="btn btn-out btn-sm" type="submit">Sign out</button></form>
    <?php else: ?>
      <a class="btn btn-out btn-sm" href="<?= e(site_url('login')) ?>">Sign in</a><a class="btn btn-amber btn-sm" href="<?= e(site_url('register')) ?>"><?= !empty($landing) ? 'Book now' : 'Register' ?></a>
    <?php endif; ?>
  </div>
</div></header>
<main>
<?php if ($_flash): ?><div class="container"><?php foreach ($_flash as [$t, $m]): ?><div class="flash <?= e($t) ?>" role="status"><?= e($m) ?></div><?php endforeach; ?></div><?php endif; ?>
