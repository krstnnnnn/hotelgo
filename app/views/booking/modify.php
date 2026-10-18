<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<div class="container section"><div class="card" style="max-width:520px;margin:0 auto">
  <div class="eyebrow">Booking <?= e($b['booking_ref']) ?></div><h1 style="font-size:2rem">Change your date or time</h1>
  <p class="mute">Now: <b><?= e(fmt_dt($b['start_at'])) ?></b> (<?= e(stay_label($b)) ?>). Same room type and same length of stay, so the price does not change. Free changes until <?= (int) setting('cancel_hours', '2') ?> hours before check-in.</p>
  <form method="post" action="<?= e(site_url('booking/' . $b['booking_ref'] . '/modify')) ?>"><?= csrf_input() ?>
    <div class="field"><label for="date">New date</label><input id="date" type="date" name="date" required min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+60 days')) ?>" value="<?= e(date('Y-m-d', strtotime($b['start_at']))) ?>"></div>
    <?php if ($b['stay_type'] === 'hourly'): ?><div class="field"><label for="time">New start time</label><select id="time" name="time"><?= time_options(date('H:i', strtotime($b['start_at']))) ?></select></div><?php endif; ?>
    <button class="btn btn-block" type="submit">Save new time</button></form>
  <p class="center small" style="margin-top:14px"><a href="<?= e(site_url('booking/' . $b['booking_ref'])) ?>">← Keep my booking as it is</a></p>
</div></div>
