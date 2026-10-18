<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<div class="container section"><div class="receipt"><div class="row between no-print" style="margin-bottom:16px"><a href="<?= e(site_url('booking/' . $b['booking_ref'])) ?>">← Back to booking</a><button class="btn" type="button" data-print>Print / Save as PDF</button></div>
<div class="card"><div class="eyebrow">Official receipt</div><h1 style="font-size:2rem"><?= e(setting('motel_name')) ?></h1><p class="mute"><?= e(setting('motel_address')) ?></p>
<table><tr><td>Booking reference</td><td><?= e($b['booking_ref']) ?></td></tr><tr><td>Guest</td><td><?= e($b['guest_name']) ?></td></tr><tr><td>Room</td><td><?= e($b['room_no']) ?> · <?= e($b['type_name']) ?></td></tr>
<tr><td>Stay</td><td><?= e(stay_label($b)) ?></td></tr><tr><td>Check-in</td><td><?= e(fmt_dt($b['start_at'])) ?></td></tr><tr><td>Check-out</td><td><?= e(fmt_dt($b['end_at'])) ?></td></tr>
<tr><td>Room rate</td><td><?= e(money($b['room_rate'])) ?></td></tr><tr><td>Online booking fee</td><td><?= e(money($b['booking_fee'])) ?></td></tr><tr><td><b>Total</b></td><td><?= e(money($b['total'])) ?></td></tr>
<?php if ($pay): ?><tr><td>Payment</td><td><?= e(pay_label($pay['method'])) ?> · <?= e($pay['txn_ref']) ?> · <?= e($pay['status']) ?></td></tr><tr><td>Paid on</td><td><?= e(fmt_dt($pay['paid_at'])) ?></td></tr><?php endif; ?></table>
<p class="small mute" style="margin-top:16px">Thank you for booking with HOTELGO. Show your booking reference and a valid ID at check-in.</p></div></div></div>
