<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<div class="container section" style="padding-top:28px"><div class="row between"><h1>My bookings</h1>
  <div class="tabs"><?php foreach (['upcoming' => 'Upcoming', 'past' => 'Past', 'cancelled' => 'Cancelled'] as $k => $n): ?><a class="<?= $tab === $k ? 'on' : '' ?>" href="<?= e(site_url('my-bookings')) ?>?tab=<?= e($k) ?>"><?= e($n) ?></a><?php endforeach; ?></div></div>
  <div class="stack" style="margin-top:20px">
  <?php foreach ($rows as $b): ?>
    <div class="card row between"><div><div class="row"><h3 style="margin:0"><?= $b['room_no'] && $b['status'] !== 'pending_payment' ? 'Room ' . e($b['room_no']) . ' · ' : '' ?><?= e($b['type_name']) ?></h3><span class="pill <?= e(status_class($b['status'])) ?>"><?= e(str_replace('_', ' ', $b['status'])) ?></span></div>
      <div class="mute small"><?= e($b['booking_ref']) ?> · <?= e(stay_label($b)) ?> · <?= e(fmt_dt($b['start_at'])) ?> · <?= e(money($b['total'])) ?></div></div>
      <a class="btn btn-sm" href="<?= e(site_url($b['status'] === 'pending_payment' ? 'pay/' . $b['booking_ref'] : 'booking/' . $b['booking_ref'])) ?>"><?= $b['status'] === 'pending_payment' ? 'Pay now' : 'View' ?></a></div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="card center mute">Nothing here yet. <a href="<?= e(site_url('rooms')) ?>">Find a room</a></div><?php endif; ?></div></div>
