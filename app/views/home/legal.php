<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$mail = (string) config_item('hotelgo_contact_email'); $tel = (string) config_item('hotelgo_contact_phone'); $loc = (string) config_item('hotelgo_contact_location');
$hold = setting('hold_minutes', '10'); $cut = setting('cancel_hours', '2'); $fee = money(setting('booking_fee', '30')); $motel = setting('motel_name', 'HOTELGO');
$day = '1 January ' . date('Y');
$docs = [
 'terms' => ['Terms of Service', [
  ['1. About these terms', 'These terms apply when you use the HOTELGO website to search for, book and pay for a room in Puerto Galera, Oriental Mindoro. By creating an account or making a booking you agree to them.'],
  ['2. Your account', 'You must give a real name, a working email address and a Philippine mobile number that you own. Keep your password private. You are responsible for what happens under your account. We may close accounts that are used to abuse the service.'],
  ['3. Bookings and prices', 'Rooms are sold by the hour (3, 6 or 12 hours) or overnight. The price shown for your chosen time is the room rate. An online booking fee of ' . $fee . ' is added at checkout. Walk-in guests who pay cash at the front desk do not pay the booking fee. A booking is confirmed only after payment is received.'],
  ['4. Room hold', 'When you choose a room it is held for ' . $hold . ' minutes while you pay. If payment is not completed in that time the hold ends and the room is released to other guests.'],
  ['5. Payment', 'Online payments are processed by PayMongo (GCash, Maya and cards). HOTELGO does not see or store your card or wallet details. If a payment arrives after your hold ended and the room is no longer free, you will be refunded in full.'],
  ['6. Check-in', 'Show your QR ticket or booking reference and a valid government ID at the front desk. We keep only the last 4 characters of your ID number. The motel may refuse check-in if the details do not match the booking.'],
  ['7. Changes by the motel', 'If a room becomes unavailable (for example maintenance), the motel may move you to an equal or better room, or refund you in full.'],
  ['8. Liability', 'We take reasonable care of guests and their stay, but the motel is not responsible for items left in rooms or for delays caused by weather, ferry schedules or events outside our control.'],
  ['9. Changes to these terms', 'We may update these terms. The date at the top of the page shows the latest version. Using the site after a change means you accept it.'],
  ['10. Contact', 'Questions about these terms: ' . $mail . ' · ' . $tel . '.'],
 ]],
 'privacy' => ['Privacy Policy', [
  ['1. Who we are', $motel . ' operates the HOTELGO booking website in Puerto Galera. We follow the Philippine Data Privacy Act of 2012 (Republic Act 10173). Contact for privacy matters: ' . $mail . '.'],
  ['2. What we collect', 'Your name, email address, mobile number, booking details (dates, room, number of guests), the type of ID you show and only the last 4 characters of the ID number, payment status and reference numbers, and technical records such as sign-in attempts and your IP address for security.'],
  ['3. Why we collect it', 'To confirm and manage your booking, send your ticket and reminders by SMS and email, verify your account with one-time codes, process refunds, prevent fraud and abuse, and keep the accounting records of the motel.'],
  ['4. What we do not collect', 'We never see or store your card number or e-wallet login. Payments are handled on the PayMongo hosted checkout page.'],
  ['5. Who can see your data', 'Front-desk staff see the bookings and guest records they need to serve you. The owner can see payments, reports and the audit log. Every time staff open a guest record it is written to an audit log. We share data with service providers only as needed to run the service (payment, SMS and email delivery).'],
  ['6. How long we keep it', 'Booking and payment records are kept for the motel\'s accounting and legal needs. Verification codes expire in minutes and are stored only as hashes. Passwords are stored only as hashes.'],
  ['7. Your rights', 'You may ask to see, correct or delete your personal data, to object to its processing, and to complain to the National Privacy Commission. Write to ' . $mail . '. Records we must keep by law or for accounting may be kept, but we will tell you which.'],
  ['8. Security', 'Sessions use secure cookies, forms are protected against forgery, sign-in and code attempts are rate limited, and staff access depends on role. No system is perfect: if a breach affects you we will tell you as the law requires.'],
  ['9. Cookies', 'We use one session cookie to keep you signed in and protect forms. We do not use advertising cookies.'],
 ]],
 'cancellation' => ['Cancellation & Refund Policy', [
  ['1. Cancelling or moving a booking', 'You can cancel or move a paid booking yourself from My bookings until ' . $cut . ' hours before your start time.'],
  ['2. Refunds', 'A paid booking cancelled in time is refunded to the same payment method. E-wallet and card refunds can take a few business days to show, depending on your provider.'],
  ['3. Late cancellation and no-show', 'After the cutoff the booking cannot be cancelled online. Please contact the motel at ' . $mail . ' or ' . $tel . '. Late cancellations and no-shows are not refunded unless the motel decides otherwise.'],
  ['4. Unpaid bookings', 'A room held for payment is released automatically after ' . $hold . ' minutes. Nothing is charged.'],
  ['5. If we cannot give you the room', 'If the motel cancels or cannot provide your room, you receive a full refund. If your payment arrived after the hold ended and the room was taken, you are refunded in full.'],
  ['6. Booking fee', 'The online booking fee (' . $fee . ') is refunded together with the room rate when a booking is cancelled in time.'],
 ]],
 'rules' => ['House Rules', [
  ['1. Check-in and check-out', 'Your stay runs for the hours you booked. Please leave on time so the room can be cleaned for the next guest. Overnight stays follow the overnight times shown on your ticket.'],
  ['2. Guests', 'The number of guests must not be higher than the number on your booking. Extra guests may be refused at the desk.'],
  ['3. Identification', 'A valid government ID is required at check-in.'],
  ['4. Respect and safety', 'Keep noise low, especially at night. No smoking inside rooms. No weapons, illegal drugs or open flames. Damage to the room or its items will be charged.'],
  ['5. Valuables', 'Keep your valuables with you. The motel is not responsible for items left behind.'],
  ['6. Staff rights', 'The motel may end a stay without refund if these rules are broken or if safety is at risk.'],
 ]],
];
[$title, $secs] = $docs[$page];
?>
<div class="container section legal">
  <a class="small" href="<?= e(site_url('')) ?>">← Back to home</a>
  <div class="eyebrow" style="margin-top:14px">HOTELGO</div><h1 style="font-size:clamp(1.8rem,4vw,2.6rem)"><?= e($title) ?></h1>
  <p class="mute small">Last updated <?= e($day) ?> · <?= e($loc) ?></p>
  <nav class="tabs no-print" style="margin:12px 0 24px;flex-wrap:wrap" aria-label="Policies">
    <a class="<?= $page === 'terms' ? 'on' : '' ?>" href="<?= e(site_url('terms')) ?>">Terms</a><a class="<?= $page === 'privacy' ? 'on' : '' ?>" href="<?= e(site_url('privacy')) ?>">Privacy</a>
    <a class="<?= $page === 'cancellation' ? 'on' : '' ?>" href="<?= e(site_url('cancellation-policy')) ?>">Cancellation</a><a class="<?= $page === 'rules' ? 'on' : '' ?>" href="<?= e(site_url('house-rules')) ?>">House rules</a></nav>
  <div class="card legal-body"><?php foreach ($secs as [$h, $t]): ?><h3><?= e($h) ?></h3><p class="mute"><?= e($t) ?></p><?php endforeach; ?></div>
  <p class="small mute" style="margin-top:16px">Questions? <a href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a> · <a href="tel:<?= e($tel) ?>"><?= e($tel) ?></a></p>
</div>
