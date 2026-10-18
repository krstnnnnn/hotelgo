<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$o = fn($k, $d = '') => e($old[$k] ?? $d);
$hold = (int) setting('hold_minutes', '10'); ?>
<div class="container section" style="padding-top:28px"><h1>Almost there, <?= e(explode(' ', $user['full_name'])[0]) ?>.</h1>
<div class="grid" style="grid-template-columns:1fr 380px;align-items:start">
<form method="post" action="<?= e(site_url('book')) ?>" class="stack"><?= csrf_input() ?>
  <?php foreach ($p as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
  <div class="card"><h3>Guest details</h3>
    <div class="grid g2"><div class="field"><label for="name">Full name</label><input id="name" name="name" required maxlength="120" value="<?= $o('name', $user['full_name']) ?>"></div>
    <div class="field"><label for="mobile">Mobile number</label><input id="mobile" name="mobile" required maxlength="20" value="<?= $o('mobile', $user['mobile']) ?>"></div>
    <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required maxlength="150" value="<?= $o('email', $user['email']) ?>"></div>
    <div class="field"><label for="id_type">Valid ID</label><select id="id_type" name="id_type" required><?php foreach (["Driver's license", 'Passport', 'UMID', 'National ID', 'Other government ID'] as $t): ?><option<?= ($old['id_type'] ?? '') === $t ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div></div>
    <div class="field"><label for="id_number">ID number</label><input id="id_number" name="id_number" required maxlength="30" autocomplete="off"><div class="help">For your privacy we save only the <b>last 4 characters</b> of your ID number.</div></div></div>
  <div class="card"><h3>Payment method</h3>
    <?php foreach (['ewallet' => ['E-wallet', 'GCash, Maya'], 'card' => ['Credit / debit card', 'Visa, Mastercard'], 'bank' => ['Bank / OTC', 'Transfer or pay at a store']] as $k => [$n, $d]): ?>
      <div class="check"><input id="pm_<?= e($k) ?>" type="radio" name="pay_method" value="<?= e($k) ?>" required<?= ($old['pay_method'] ?? 'ewallet') === $k ? ' checked' : '' ?>><label for="pm_<?= e($k) ?>"><b><?= e($n) ?></b> · <span class="mute"><?= e($d) ?></span></label></div>
    <?php endforeach; ?>
    <div class="flash info">You will be taken to our payment partner's secure window, then returned here with your confirmation. Your room is held for <?= $hold ?> minutes.</div>
    <div class="check" style="margin-top:16px"><input id="agree" type="checkbox" name="agree" value="1" required><label for="agree">I agree to the house rules, cancellation policy and privacy notice.</label></div>
    <button class="btn btn-block" type="submit">Pay <?= e(money($rate + $fee)) ?> and confirm</button></div>
</form>
<aside class="card"><div class="scene t2" style="border-radius:16px;margin-bottom:16px"><i></i></div><h3><?= e($type['name']) ?></h3><p class="mute"><?= e(setting('motel_name')) ?> · Puerto Galera</p>
  <div class="stack small"><div class="row between"><span class="mute">Stay</span><b><?= $p['stay'] === 'overnight' ? 'Overnight' : 'Hourly · ' . (int) $w['hours'] . ' hrs' ?></b></div>
  <div class="row between"><span class="mute">Check-in</span><b><?= e(fmt_dt($w['start'])) ?></b></div><div class="row between"><span class="mute">Check-out</span><b><?= e(fmt_dt($w['end'])) ?></b></div>
  <div class="row between"><span class="mute">Guests</span><b><?= (int) $p['guests'] ?></b></div><div class="row between"><span class="mute">Room</span><b>Assigned after payment</b></div><hr>
  <div class="row between"><span class="mute">Room rate</span><span><?= e(money($rate)) ?></span></div><div class="row between"><span class="mute">Online booking fee</span><span><?= e(money($fee)) ?></span></div>
  <div class="row between"><b>Total</b><span class="price"><?= e(money($rate + $fee)) ?></span></div></div>
  <div class="flash ok small">Free cancellation until <?= (int) setting('cancel_hours', '2') ?> hours before check-in.</div></aside>
</div></div>
