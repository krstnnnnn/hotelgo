<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); $left = max(0, strtotime($b['hold_expires_at']) - time()); $names = Paymongo::WALLETS; ?>
<div class="container section"><div class="card" style="max-width:560px;margin:0 auto">
  <span class="pill">Secure payment · PayMongo</span><h1 style="font-size:2rem;margin-top:12px">Pay for your room</h1>
  <p class="mute">Room held for <b data-countdown="<?= (int) $left ?>"></b>. After you pay, this page confirms by itself.</p>
  <div class="stack small"><div class="row between"><span class="mute">Booking</span><b><?= e($b['booking_ref']) ?></b></div><div class="row between"><span class="mute">Room type</span><b><?= e($b['type_name']) ?></b></div>
    <div class="row between"><span class="mute">Stay</span><b><?= e(fmt_dt($b['start_at'])) ?></b></div><div class="row between"><b>Amount due</b><span class="price"><?= e(money($b['total'])) ?></span></div></div>

  <h3 style="margin:22px 0 8px">E-wallet</h3>
  <div class="grid" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
    <?php foreach ($wallets as $w): ?>
      <form method="post" action="<?= e(site_url('pay/' . $b['booking_ref'] . '/wallet')) ?>"><?= csrf_input() ?><input type="hidden" name="wallet" value="<?= e($w) ?>"><button class="btn btn-amber btn-block" type="submit"><?= e($names[$w] ?? $w) ?></button></form>
    <?php endforeach; ?>
  </div>
  <h3 style="margin:22px 0 8px">Card, online banking or QR</h3>
  <form method="post" action="<?= e(site_url('pay/' . $b['booking_ref'] . '/link')) ?>"><?= csrf_input() ?><button class="btn btn-out btn-block" type="submit">Open payment link</button></form>
  <p class="mute small" id="paystate" style="margin-top:14px" aria-live="polite"></p>
  <form method="post" action="<?= e(site_url('booking/' . $b['booking_ref'] . '/cancel')) ?>" style="margin-top:10px"><?= csrf_input() ?><button class="btn btn-out btn-block" type="submit">Cancel</button></form>
</div></div>
<script>
(function () {
  var box = document.getElementById('paystate'), url = <?= json_encode(site_url('pay/' . $b['booking_ref'] . '/status')) ?>, done = <?= json_encode(site_url('booking/' . $b['booking_ref'])) ?>;
  function poll() {
    fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      if (j.state === 'paid') { box.textContent = 'Payment received!'; location.href = done; return; }
      if (j.state === 'pending_payment') { setTimeout(poll, 8000); return; }
      location.href = done;
    }).catch(function () { setTimeout(poll, 15000); });
  }
  setTimeout(poll, 8000);
})();
</script>
