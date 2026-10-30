<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$ok = in_array($b['status'], ['confirmed', 'checked_in'], true);
$first = explode(' ', $b['guest_name'])[0];
$gcal = 'https://calendar.google.com/calendar/render?' . http_build_query(['action' => 'TEMPLATE', 'text' => 'HOTELGO · Room ' . $b['room_no'], 'dates' => gmdate('Ymd\THis\Z', strtotime($b['start_at'])) . '/' . gmdate('Ymd\THis\Z', strtotime($b['end_at'])), 'location' => hotel_of($b, 'name') . ', ' . hotel_of($b, 'address')]);
$remindAt = fmt_dt(date('Y-m-d H:i:s', strtotime($b['start_at']) - 1800), 'g:i A'); ?>
<?php if ($ok): ?>
<section class="done"><div class="container row"><div class="tick"><?= icon('check', '#0B2233', 34) ?></div><div><h1>You're booked, <?= e($first) ?>!</h1><p>Payment received. Your room has been assigned and confirmations are on their way.</p></div></div>
  <svg class="waves" viewBox="0 0 1200 70" preserveAspectRatio="none" aria-hidden="true"><path d="M0 30c150-40 300 30 450 0s300-30 450 0 200 20 300 0v40H0z" fill="#0A5F67"/><path d="M0 48c180-30 330 24 500 0s300-24 450 0 160 12 250 0v22H0z" fill="#0E7C86"/><path d="M0 62c200-20 360 14 560 0s330-14 480 0 110 8 160 0v8H0z" fill="#F3F7F9"/></svg></section>
<?php endif; ?>
<div class="container section" style="padding-top:<?= $ok ? '0' : '28px' ?>"><div class="tgrid">
<div class="stack">
  <div class="ticket2<?= $ok ? '' : ' plain' ?>"><div class="tmain"><div class="eyebrow"><?= e(hotel_of($b, 'name')) ?> · Puerto Galera</div>
    <?php if ($ok): ?><div class="bigroom">Room <?= e($b['room_no']) ?></div><?php else: ?><h2 style="margin:6px 0"><?= e(ucfirst(str_replace('_', ' ', $b['status']))) ?></h2><?php endif; ?>
    <p class="mute" style="margin:0"><?= e($b['type_name']) ?> <span class="pill <?= e(status_class($b['status'])) ?>"><?= e(str_replace('_', ' ', $b['status'])) ?></span></p>
    <div class="kv"><div><span class="eyebrow">Check-in</span><b><?= e(fmt_dt($b['start_at'])) ?></b></div><div><span class="eyebrow">Check-out</span><b><?= e(fmt_dt($b['end_at'])) ?></b></div>
      <div><span class="eyebrow">Stay</span><b><?= e(stay_label($b)) ?> · <?= (int) $b['guests'] ?> guests</b></div><div><span class="eyebrow">Paid</span><b><?= e(money($b['total'])) ?> · <?= e(pay_label($b['pay_method'])) ?></b></div></div>
    <div class="row">
      <?php if ($ok): ?><a class="btn btn-pill" href="<?= e(site_url('booking/' . $b['booking_ref'] . '/receipt')) ?>">Download receipt</a><?php endif; ?>
      <?php if ($can_modify): ?><a class="btn btn-pill" href="<?= e(site_url('booking/' . $b['booking_ref'] . '/modify')) ?>">Modify</a><?php endif; ?>
      <?php if ($b['status'] === 'pending_payment'): ?><a class="btn btn-amber btn-pill" href="<?= e(site_url('pay/' . $b['booking_ref'])) ?>">Pay now</a><?php endif; ?>
      <?php if ($can_cancel): ?><form class="inline" method="post" action="<?= e(site_url('booking/' . $b['booking_ref'] . '/cancel')) ?>" data-confirm="Cancel this booking? Your payment will be refunded."><?= csrf_input() ?><button class="btn btn-pill danger" type="submit">Cancel booking</button></form><?php endif; ?>
      <a class="btn btn-pill" href="<?= e(site_url('my-bookings')) ?>">← My bookings</a></div></div>
  <div class="tside"><?php if ($ok): ?><div class="qrbox"><?= qr_svg(abs_url('admin/reservations/' . $b['booking_ref'])) ?></div><div class="label">Booking ref</div><div class="ref"><?= e($b['booking_ref']) ?></div><p class="small mute" style="margin:6px 0 0">Show this QR at check-in<br>with a valid ID.</p>
    <?php else: ?><div class="label">Booking ref</div><div class="ref"><?= e($b['booking_ref']) ?></div><p class="small mute" style="margin:6px 0 0">The QR code appears after payment.</p><?php endif; ?></div></div>
  <?php if ($ok): ?><div class="mapcard"><?php if (config_item('google_maps_key') !== ''): ?><iframe title="Map of <?= e(hotel_of($b, 'name')) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.google.com/maps/embed/v1/place?key=<?= e(config_item('google_maps_key')) ?>&amp;q=<?= e(hotel_of($b, 'lat') . ',' . hotel_of($b, 'lng')) ?>"></iframe>
    <?php else: ?><iframe title="Map of <?= e(hotel_of($b, 'name')) ?>" loading="lazy" referrerpolicy="no-referrer" src="<?= e(osm_embed_url($b)) ?>"></iframe><?php endif; ?>
    <div class="info"><h3 style="margin:0 0 6px"><?= e(hotel_of($b, 'name')) ?></h3><p class="mute small"><?= e(hotel_of($b, 'address')) ?></p><a class="btn btn-ink btn-sm" target="_blank" rel="noopener" href="<?= e(maps_link($b)) ?>">Get directions</a></div></div><?php endif; ?>
  <?php if (!empty($b['is_vip']) && in_array($b['status'], ['confirmed', 'checked_in'], true)): ?>
  <div class="card vipbox"><div class="ctitle"><span class="ico"><?= icon('star', '#B7791F') ?></span><h3>Your VIP package</h3></div>
    <?php foreach (included_list($b) as [$t, $d]): ?><div class="row" style="gap:8px;margin:4px 0"><?= icon('check', '#0E7C86', 16) ?><span><b><?= e($t) ?></b><?= $d !== '' ? ' <span class="mute small">· ' . e($d) . '</span>' : '' ?></span></div><?php endforeach; ?>
    <h4 style="margin:18px 0 8px">Your requests</h4>
    <?php if (empty($requests)): ?><p class="mute small">Nothing requested yet. Need something else? Ask below.</p><?php endif; ?>
    <?php foreach ($requests as $r): ?><div class="row between" style="gap:10px;margin:8px 0;align-items:flex-start"><div><b><?= e($r['service']) ?></b><?= $r['note'] !== '' ? '<div class="mute small">' . e($r['note']) . '</div>' : '' ?>
      <?php if ((float) $r['price'] > 0): ?><div class="small">Price: <b><?= e(money($r['price'])) ?></b><?= $r['paid_at'] ? ' · paid' : '' ?></div><?php endif; ?></div>
      <div class="row" style="gap:8px"><span class="pill <?= e(match ($r['status']) { 'accepted', 'done' => 'ok', 'declined' => 'bad', default => 'warn' }) ?>"><?= e($r['status']) ?></span>
      <?php if ($online && (float) $r['price'] > 0 && empty($r['paid_at']) && in_array($r['status'], ['accepted', 'done'], true)): ?><form class="inline" method="post" action="<?= e(site_url('booking/' . $b['booking_ref'] . '/request/' . (int) $r['id'] . '/pay')) ?>"><?= csrf_input() ?><button class="btn btn-sm" type="submit">Pay online</button></form><?php endif; ?></div></div><?php endforeach; ?>
    <form method="post" action="<?= e(site_url('booking/' . $b['booking_ref'] . '/request')) ?>" class="stack" style="margin-top:14px"><?= csrf_input() ?>
      <div class="field"><label for="rq_service">What do you need?</label><select id="rq_service" name="service" required><?php foreach ($services as $sv): ?><option><?= e(is_array($sv) ? ($sv['name'] ?? '') : $sv) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="rq_note">Details (optional)</label><input id="rq_note" name="note" maxlength="500" placeholder="e.g. 8 PM, for 4 people"></div>
      <button class="btn" type="submit">Send request</button>
      <p class="mute small" style="margin:0">Extras may have a price. The team will confirm it before anything is charged. You can pay at the desk or online.</p></form></div>
  <?php endif; ?>
</div>
<aside class="stack next"><h2 style="font-size:1.5rem">What happens next</h2>
  <?php if ($sms): ?><div class="card"><div class="head"><span class="bubble"><?= icon('message', '#fff', 18) ?></span><b><?= $sms['status'] === 'logged' ? 'SMS (demo, not sent)' : 'SMS sent' ?></b><span class="pill <?= e(status_class($sms['status'])) ?>"><?= e(notice_label($sms['status'])) ?></span></div><div class="msgbox"><?= e($sms['message']) ?></div></div><?php endif; ?>
  <?php if ($mail): ?><div class="card"><div class="head"><span class="bubble"><?= icon('mail', '#fff', 18) ?></span><b><?= $mail['status'] === 'logged' ? 'Email (demo, not sent)' : 'Email sent' ?></b><span class="pill <?= e(status_class($mail['status'])) ?>"><?= e(notice_label($mail['status'])) ?></span></div><div class="msgbox"><b><?= e($mail['subject']) ?></b><br><span class="mute small">To <?= e($mail['recipient']) ?> · Booking details and directions included</span></div></div><?php endif; ?>
  <?php if ($ok): ?><div class="card"><div class="head"><span class="bubble amber"><?= icon('calendar', '#0B2233', 18) ?></span><b>Add it to your calendar</b></div><p class="mute small">Saves the stay with a reminder 30 minutes before check-in.</p>
    <a class="btn btn-block" href="<?= e(site_url('booking/' . $b['booking_ref'] . '/calendar.ics')) ?>">Add to calendar</a><a class="btn btn-pill btn-block" style="margin-top:8px" target="_blank" rel="noopener" href="<?= e($gcal) ?>">Open in Google Calendar</a></div>
    <div class="remind"><?= icon('bell', '#7A4B00', 20) ?><span>Your calendar reminder goes off at <b><?= e($remindAt) ?></b>, 30 minutes before check-in.</span></div><?php endif; ?>
</aside></div></div>
