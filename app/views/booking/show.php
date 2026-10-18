<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$ok = in_array($b['status'], ['confirmed', 'checked_in'], true);
$gcal = 'https://calendar.google.com/calendar/render?' . http_build_query(['action' => 'TEMPLATE', 'text' => 'HOTELGO · Room ' . $b['room_no'], 'dates' => gmdate('Ymd\THis\Z', strtotime($b['start_at'])) . '/' . gmdate('Ymd\THis\Z', strtotime($b['end_at'])), 'location' => setting('motel_name') . ', ' . setting('motel_address')]); ?>
<?php if ($ok): ?><div class="done"><div class="container row"><div class="tick">✓</div><div><h1 style="margin:0">You're booked, <?= e(explode(' ', $b['guest_name'])[0]) ?>!</h1><div style="color:#C5D5DD">Payment received. Your room has been assigned and confirmations were sent.</div></div></div></div><?php endif; ?>
<div class="container section" style="padding-top:28px"><div class="grid" style="grid-template-columns:1fr 360px;align-items:start">
<div class="stack"><div class="ticket"><div class="main"><div class="eyebrow"><?= e(setting('motel_name')) ?></div>
  <?php if ($ok): ?><div class="bigroom">Room <?= e($b['room_no']) ?></div><?php else: ?><h2><?= e(str_replace('_', ' ', ucfirst($b['status']))) ?></h2><?php endif; ?>
  <p class="mute"><?= e($b['type_name']) ?> <span class="pill <?= e(status_class($b['status'])) ?>"><?= e(str_replace('_', ' ', $b['status'])) ?></span></p>
  <div class="kv"><div><span class="eyebrow">Check-in</span><b><?= e(fmt_dt($b['start_at'])) ?></b></div><div><span class="eyebrow">Check-out</span><b><?= e(fmt_dt($b['end_at'])) ?></b></div>
    <div><span class="eyebrow">Stay</span><b><?= e(stay_label($b)) ?> · <?= (int) $b['guests'] ?> guests</b></div><div><span class="eyebrow">Total</span><b><?= e(money($b['total'])) ?></b></div>
    <div><span class="eyebrow">Guest</span><b><?= e($b['guest_name']) ?></b></div><div><span class="eyebrow">ID on file</span><b><?= e($b['id_type']) ?> · ••••<?= e($b['id_last4']) ?></b></div></div></div>
  <div class="side"><div class="eyebrow">Booking ref</div><div class="ref"><?= e($b['booking_ref']) ?></div><p class="small mute">Show this reference at check-in with a valid ID.</p></div></div>
<?php if ($ok): ?><div class="card"><h3>Get there</h3><p class="mute"><?= e(setting('motel_name')) ?> · <?= e(setting('motel_address')) ?></p>
  <?php if (env_get('GOOGLE_MAPS_KEY') !== ''): ?><iframe title="Map" width="100%" height="260" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.google.com/maps/embed/v1/place?key=<?= e(env_get('GOOGLE_MAPS_KEY')) ?>&amp;q=<?= e(setting('motel_lat') . ',' . setting('motel_lng')) ?>"></iframe><?php endif; ?>
  <div class="row" style="margin-top:12px"><a class="btn btn-ink" target="_blank" rel="noopener" href="<?= e(maps_link()) ?>">Get directions</a></div></div><?php endif; ?></div>
<aside class="stack">
  <?php if ($ok): ?><div class="card"><h3>Add it to your calendar</h3><p class="mute small">Saves the stay with a reminder 30 minutes before check-in.</p><div class="stack">
    <a class="btn btn-block" href="<?= e(site_url('booking/' . $b['booking_ref'] . '/calendar.ics')) ?>">Download calendar file (.ics)</a><a class="btn btn-out btn-block" target="_blank" rel="noopener" href="<?= e($gcal) ?>">Add to Google Calendar</a></div></div><?php endif; ?>
  <?php if ($b['status'] === 'pending_payment'): ?><a class="btn btn-amber btn-block" href="<?= e(site_url('pay/' . $b['booking_ref'])) ?>">Pay now</a><?php endif; ?>
  <?php if ($can_cancel): ?><form method="post" action="<?= e(site_url('booking/' . $b['booking_ref'] . '/cancel')) ?>" data-confirm="Cancel this booking? Your payment will be refunded."><?= csrf_input() ?><button class="btn btn-out btn-block" type="submit">Cancel booking</button></form><?php endif; ?>
  <a class="btn btn-out btn-block" href="<?= e(site_url('my-bookings')) ?>">← My bookings</a></aside></div></div>
