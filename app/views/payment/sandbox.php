<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); $left = max(0, strtotime($b['hold_expires_at']) - time()); ?>
<div class="container section"><div class="card" style="max-width:520px;margin:0 auto">
  <span class="pill warn">SANDBOX · no real money is charged</span><h1 style="font-size:2rem;margin-top:12px">Secure payment</h1>
  <p class="mute">This page pretends to be the payment partner (GCash / card / bank). Room held for <b data-countdown="<?= (int) $left ?>"></b></p>
  <div class="stack small"><div class="row between"><span class="mute">Booking</span><b><?= e($b['booking_ref']) ?></b></div><div class="row between"><span class="mute">Room type</span><b><?= e($b['type_name']) ?></b></div>
    <div class="row between"><span class="mute">Stay</span><b><?= e(fmt_dt($b['start_at'])) ?></b></div><div class="row between"><b>Amount due</b><span class="price"><?= e(money($b['total'])) ?></span></div></div>
  <form method="post" action="<?= e(site_url('pay/' . $b['booking_ref'] . '/confirm')) ?>" style="margin-top:20px"><?= csrf_input() ?><button class="btn btn-amber btn-block" type="submit">Pay <?= e(money($b['total'])) ?> (sandbox)</button></form>
  <form method="post" action="<?= e(site_url('booking/' . $b['booking_ref'] . '/cancel')) ?>" style="margin-top:10px"><?= csrf_input() ?><button class="btn btn-out btn-block" type="submit">Cancel</button></form>
</div></div>
