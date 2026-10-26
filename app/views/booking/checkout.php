<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$o = fn($k, $d = '') => e($old[$k] ?? $d);
$hold = (int) setting('hold_minutes', '10');
$methods = ['ewallet' => ['wallet', 'E-wallet', 'Pay with your wallet app'], 'card' => ['card', 'Credit / debit card', 'Visa, Mastercard'], 'bank' => ['bank', 'Bank / OTC', 'Transfer or pay at a store']];
$sel = $old['pay_method'] ?? 'ewallet'; ?>
<div class="container section" style="padding-top:24px">
<div class="steps" aria-label="Booking steps" style="margin-bottom:24px"><span class="s passed"><span class="c">✓</span>Search</span><span class="line"></span><span class="s passed"><span class="c">✓</span>Choose room</span><span class="line"></span><span class="s now"><span class="c">3</span>Details &amp; payment</span><span class="line"></span><span class="s"><span class="c">4</span>Confirmed</span></div>
<h1>Almost there, <?= e(explode(' ', $user['full_name'])[0]) ?>.</h1>
<div class="grid" style="grid-template-columns:minmax(0,1fr) 380px;align-items:start">
<form method="post" action="<?= e(site_url('book')) ?>" class="stack"><?= csrf_input() ?>
  <?php foreach ($p as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
  <div class="card"><div class="ctitle"><span class="ico"><?= icon('user', '#0E7C86') ?></span><h3>Guest details</h3></div>
    <div class="grid g2"><div class="field"><label for="name">Full name</label><input id="name" name="name" required maxlength="120" value="<?= $o('name', $user['full_name']) ?>"></div>
    <div class="field"><label for="mobile">Mobile number</label><input id="mobile" name="mobile" required maxlength="20" value="<?= $o('mobile', $user['mobile']) ?>"></div>
    <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required maxlength="150" value="<?= $o('email', $user['email']) ?>"></div>
    <div class="field"><label for="id_type">Valid ID</label><select id="id_type" name="id_type" required><?php foreach (["Driver's license", 'Passport', 'UMID', 'National ID', 'Other government ID'] as $t): ?><option<?= ($old['id_type'] ?? '') === $t ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div></div>
    <div class="field"><label for="id_number">ID number</label><input id="id_number" name="id_number" required maxlength="30" autocomplete="off"><div class="help">For your privacy we save only the <b>last 4 characters</b> of your ID number.</div></div>
    <div class="hint"><?= icon('bell', '#4F6574', 18) ?><span>We will text and email your booking confirmation.</span></div></div>
  <div class="card"><div class="ctitle"><span class="ico"><?= icon('card', '#0E7C86') ?></span><h3>Payment method</h3><span class="lockline"><?= icon('lock', '#4F6574', 15) ?>Encrypted &amp; secure</span></div>
    <div class="pay-grid" role="radiogroup" aria-label="Payment method">
    <?php foreach ($methods as $k => [$ic, $n, $d]): ?>
      <div><input class="pm" id="pm_<?= e($k) ?>" type="radio" name="pay_method" value="<?= e($k) ?>" required<?= $sel === $k ? ' checked' : '' ?>><label class="pay-card" for="pm_<?= e($k) ?>"><span class="dot"></span><span class="pi"><?= icon($ic, '#0B2233', 26) ?></span><b><?= e($n) ?></b><small><?= e($d) ?></small></label></div>
    <?php endforeach; ?></div>
    <div class="hint"><?= icon('shield', '#0E7C86', 18) ?><span>You will be taken to our payment partner's secure window to pay <b><?= e(money($rate + $fee)) ?></b>, then returned here with your confirmation. Your room is held for <?= $hold ?> minutes.</span></div>
    <div class="check" style="margin:18px 0 16px"><input id="agree" type="checkbox" name="agree" value="1" required><label for="agree">I agree to the house rules, cancellation policy and privacy notice.</label></div>
    <button class="btn btn-block btn-lg" type="submit"><?= icon('lock', '#fff', 18) ?> Pay <?= e(money($rate + $fee)) ?> and confirm</button></div>
</form>
<aside class="card"><div class="scene t2" style="border-radius:16px;margin-bottom:16px"><i></i></div><h3><?= e($type['name']) ?></h3><p class="mute"><?= e($type['hotel_name'] ?? setting('motel_name')) ?> · <?= e($type['hotel_area'] ?? 'Puerto Galera') ?></p>
  <div class="stack small"><div class="row between"><span class="mute">Stay</span><b><?= $p['stay'] === 'overnight' ? 'Overnight' : 'Hourly · ' . (int) $w['hours'] . ' hrs' ?></b></div>
  <div class="row between"><span class="mute">Check-in</span><b><?= e(fmt_dt($w['start'])) ?></b></div><div class="row between"><span class="mute">Check-out</span><b><?= e(fmt_dt($w['end'])) ?></b></div>
  <div class="row between"><span class="mute">Guests</span><b><?= (int) $p['guests'] ?></b></div><div class="row between"><span class="mute">Room</span><b style="color:var(--teal)">Assigned after payment</b></div><hr>
  <div class="row between"><span class="mute">Room rate</span><span><?= e(money($rate)) ?></span></div><div class="row between"><span class="mute">Online booking fee</span><span><?= e(money($fee)) ?></span></div>
  <div class="row between"><b>Total</b><span class="price"><?= e(money($rate + $fee)) ?></span></div></div>
  <div class="flash ok small">Free cancellation until <?= (int) setting('cancel_hours', '2') ?> hours before check-in.</div></aside>
</div></div>
