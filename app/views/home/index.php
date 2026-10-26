<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$img = fn(string $n) => base_url('assets/img/' . $n);
/* Real photo slot: put photos/<slot>.jpg (or .jpeg .webp .png) in public/assets/img/photos and it replaces the drawn scene automatically. */
$photo = function (string $slot, string $fallback) use ($img): string {
    foreach (['jpg', 'jpeg', 'webp', 'png'] as $x) { if (is_file(ROOT_DIR . 'public/assets/img/photos/' . $slot . '.' . $x)) return base_url('assets/img/photos/' . $slot . '.' . $x); }
    return $img($fallback);
};
$mail = (string) config_item('hotelgo_contact_email'); $tel = (string) config_item('hotelgo_contact_phone'); $loc = (string) config_item('hotelgo_contact_location');
$lat = (float) setting('motel_lat', '13.5188'); $lng = (float) setting('motel_lng', '120.9647'); $motel = setting('motel_name', 'HOTELGO');
$osm = fn(float $la, float $lo, float $d) => 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode(($lo - $d) . ',' . ($la - $d * .6) . ',' . ($lo + $d) . ',' . ($la + $d * .6)) . '&layer=mapnik&marker=' . rawurlencode($la . ',' . $lo);
$places = [];
foreach ($hotels as $hh) { $places[] = ['name' => $hh['name'], 'area' => trim(($hh['area'] ?? '') . ' · ' . ($hh['address'] ?? ''), ' ·'), 'lat' => (float) $hh['lat'], 'lng' => (float) $hh['lng']]; }
if (!$places) $places[] = ['name' => $motel, 'area' => setting('motel_address', $loc), 'lat' => $lat, 'lng' => $lng];
$gallery = [['gallery1', 'g1.svg', 'Sunset over the bay', 'Sunsets'], ['gallery2', 'g5.svg', 'Palm-lined beach', 'Beach'], ['gallery3', 'g3.svg', 'A quiet room with a sea view', 'Rooms'], ['gallery4', 'g4.svg', 'The reefs of Puerto Galera', 'Reefs'], ['gallery5', 'g2.svg', 'Bangka boats at midday', 'Boats'], ['gallery6', 'g6.svg', 'Night terrace under the lanterns', 'Evenings']];
$offers = [
  ['Hourly or overnight', 'Book 3, 6 or 12 hours, or a full overnight stay. Pay only for the time you need.', 'M12 7v5l3 2M12 3a9 9 0 100 18 9 9 0 000-18z'],
  ['Room assigned instantly', 'Pay online and a free room is assigned right away. No front-desk queue.', 'M5 12l4 4 10-10'],
  ['Secure online payment', 'GCash, Maya or card through PayMongo. Card details never touch our server.', 'M4 8h16v10H4zM4 11h16'],
  ['Ticket with QR code', 'Show the QR at the desk. Add it to your calendar and download the receipt.', 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 18h2v2h-2z'],
  ['Free changes', 'Move your time or cancel before the cutoff and your payment is refunded.', 'M4 12a8 8 0 0114-5l2-2v6h-6l2-2a5 5 0 00-9 3M20 12a8 8 0 01-14 5l-2 2v-6h6l-2 2a5 5 0 009-3'],
  ['Privacy first', 'We keep only the last 4 digits of your ID. Only staff you need can see your record.', 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z'],
];
$faq = [
  ['How does hourly booking work?', 'Choose your date, start time and 3, 6 or 12 hours. We show the room types that are free for that exact time. Pay online and a room is assigned automatically.'],
  ['How long is my room held while I pay?', 'Your room is held for ' . setting('hold_minutes', '10') . ' minutes after you choose it. If payment is not completed in time the hold ends and the room is released.'],
  ['Can I cancel or move my booking?', 'Yes, up to ' . setting('cancel_hours', '2') . ' hours before your start time from the My bookings page. A paid booking that is cancelled in time is refunded. See the Cancellation & Refund Policy for details.'],
  ['What is the booking fee?', 'A small online booking fee of ' . money(setting('booking_fee', '30')) . ' is added to the room rate. Walk-in guests who pay cash at the desk do not pay it.'],
  ['What do I need at check-in?', 'Show the QR code or reference number on your ticket and a valid ID. We store only the last 4 characters of the ID number.'],
  ['Is it safe to pay online?', 'Payments run through PayMongo hosted checkout (GCash, Maya, card). Your card or wallet details are never typed on our site.'],
  ['I did not get my SMS or email. What now?', 'Open My bookings after signing in: your ticket and room number are always there. You can also write to us at ' . $mail . '.'],
];
$voices = [
  ['Booking took less than two minutes and the room was ready when we arrived.', 'Sample guest · Hourly stay'],
  ['I liked paying by GCash and getting the QR ticket on my phone. No waiting at the desk.', 'Sample guest · Overnight stay'],
  ['Clean room, calm evening, easy to move our time when the ferry was late.', 'Sample guest · Changed booking'],
];
?>
<section class="lp-hero" id="top">
  <img class="lp-hero-img" src="<?= e($photo('hero', 'hero.svg')) ?>" alt="" fetchpriority="high" data-parallax>
  <div class="lp-hero-shade"></div>
  <div class="container lp-hero-in">
    <span class="lp-eyebrow fade-up">Welcome to Puerto Galera</span>
    <h1 class="fade-up d1">Rest by the sea,<br><em>by the hour</em> or overnight.</h1>
    <p class="fade-up d2">Pick your time, pay online and we assign a free room right away. Your ticket lands on your phone and inbox.</p>
    <div class="row fade-up d3" style="justify-content:center"><a class="btn btn-fill btn-lg" href="#search">Reserve your stay</a><a class="btn btn-line btn-lg" href="#about">Discover more</a></div>
    <a class="lp-scroll fade-up d4" href="#search" aria-label="Scroll down"><span>Scroll</span><i></i></a>
  </div>
</section>

<section class="search" id="search"><div class="container"><div class="card reveal"><?= search_form(['stay' => 'hourly', 'date' => date('Y-m-d'), 'time' => '19:00', 'hours' => 3, 'guests' => 2]) ?></div></div></section>

<section class="section lp-about" id="about"><div class="container grid g2 lp-center">
  <div class="lp-frame reveal"><img src="<?= e($photo('about', 'g5.svg')) ?>" alt="A Puerto Galera beach" loading="lazy"><span class="lp-badge"><b>24/7</b><small>online<br>booking</small></span></div>
  <div class="reveal d1"><div class="eyebrow">Our story</div><h2>A calmer way to <em>stay by the sea</em></h2>
    <p class="mute">HOTELGO is built for the beach town: short stays between dives and ferries, quiet nights after the beach, and last-minute plans. See what is free for the exact time you want and book it in a few taps.</p>
    <p class="mute">Every room type shows its real price for your hours, and the motel team sees your booking the moment it is paid.</p>
    <ul class="lp-stats"><li><b>3 · 6 · 12</b><span>Hours or overnight</span></li><li><b><?= e(setting('hold_minutes', '10')) ?> min</b><span>Room hold</span></li><li><b>QR</b><span>Ticket, no queue</span></li></ul></div>
</div></section>

<section class="lp-bandq"><img class="lp-bandq-img" src="<?= e($photo('quote', 'g1.svg')) ?>" alt="" loading="lazy" data-parallax2><div class="lp-bandq-shade"></div>
  <div class="container center reveal"><blockquote>“Your stay, your hours.<br>The sea does the rest.”</blockquote><p>HOTELGO · Puerto Galera</p></div></section>

<section class="section" id="hotels"><div class="container"><div class="center"><div class="eyebrow reveal">One platform, many hotels</div><h2 class="reveal">Hotels across <em>Puerto Galera</em></h2>
  <p class="mute reveal" style="max-width:640px;margin:0 auto">Compare hotels in Sabang, White Beach, Talipanan and more. Pick the area you like, then book a room in one place.</p></div>
  <div class="grid g3 lp-rooms"><?php foreach ($hotels as $i => $hh): ?>
    <article class="card flat lp-room reveal d<?= $i % 3 ?>"><div class="lp-room-body"><div class="small mute" style="font-weight:700;letter-spacing:.06em;text-transform:uppercase"><?= e($hh['area'] ?? '') ?></div><h3><?= e($hh['name']) ?></h3><p class="mute small"><?= e($hh['description'] ?? '') ?></p>
      <div class="lp-prices"><div><span class="small mute">From</span><b><?= e(money($hh['from_price'])) ?> / 3 hrs</b></div><a class="btn btn-sm" href="<?= e(site_url('rooms')) ?>?stay=hourly&amp;date=<?= e(date('Y-m-d')) ?>&amp;time=19:00&amp;hours=3&amp;guests=2&amp;hotel=<?= (int) $hh['id'] ?>">View rooms</a></div></div></article>
  <?php endforeach; ?></div></div></section>

<section class="section" id="rooms"><div class="container"><div class="center"><div class="eyebrow reveal">Accommodations</div><h2 class="reveal">Our <em>Rooms &amp; Suites</em></h2>
  <p class="mute reveal" style="max-width:620px;margin:0 auto">Each room is a quiet place to rest. Prices are the room rate set by each hotel; a small online booking fee applies at checkout.</p></div>
  <div class="grid g3 lp-rooms"><?php foreach ($types as $i => $t): $fb = ['g3.svg', 'g1.svg', 'g5.svg', 'g4.svg'][$i % 4]; ?>
    <article class="card flat lp-room reveal d<?= $i % 3 ?>"><div class="lp-room-img"><img src="<?= e($photo('room' . ($i % 4 + 1), $fb)) ?>" alt="<?= e($t['name']) ?>" loading="lazy"><span class="lp-tag">From <?= e(money($t['price_3h'])) ?> / 3 hrs</span></div>
      <div class="lp-room-body"><div class="small mute" style="font-weight:700;letter-spacing:.06em;text-transform:uppercase"><?= e($t['hotel_name'] ?? '') ?> · <?= e($t['hotel_area'] ?? '') ?></div><h3><?= e($t['name']) ?></h3><p class="mute small"><?= e($t['description']) ?></p>
        <ul class="lp-amen"><?php foreach (array_slice(explode(',', (string) $t['amenities']), 0, 4) as $a): ?><li><?= e(trim($a)) ?></li><?php endforeach; ?><li>Up to <?= (int) $t['max_guests'] ?> guests</li></ul>
        <div class="lp-prices"><div><span class="small mute">Overnight</span><b><?= e(money($t['price_overnight'])) ?></b></div><a class="btn btn-sm" href="<?= e(site_url('rooms')) ?>?stay=hourly&amp;date=<?= e(date('Y-m-d')) ?>&amp;time=19:00&amp;hours=3&amp;guests=2&amp;hotel=<?= (int) $t['hotel_id'] ?>">Book now</a></div></div></article>
  <?php endforeach; ?></div></div></section>

<section class="section lp-dark" id="offer"><div class="container"><div class="center"><div class="eyebrow reveal" style="color:#F5A524">What we offer</div><h2 class="reveal">Everything for <em>a short stay</em></h2></div>
  <div class="grid g3 lp-offers"><?php foreach ($offers as $i => [$h, $d, $path]): ?>
    <div class="lp-offer reveal d<?= $i % 3 ?>"><span class="ico2"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#F5A524" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?= e($path) ?>"/></svg></span><h3><?= e($h) ?></h3><p><?= e($d) ?></p></div>
  <?php endforeach; ?></div></div></section>

<section class="section" id="how"><div class="container"><div class="center"><div class="eyebrow reveal">How it works</div><h2 class="reveal">Booked in <em>three steps</em></h2></div>
  <ol class="lp-steps"><li class="reveal"><span class="num">1</span><h3>Pick your time</h3><p class="mute">Choose hourly or overnight, then your date, start time and guests.</p></li>
  <li class="reveal d1"><span class="num">2</span><h3>Pay online, securely</h3><p class="mute">Pay with GCash, Maya or card. Your receipt is sent right away.</p></li>
  <li class="reveal d2"><span class="num">3</span><h3>Get your room</h3><p class="mute">A free room is assigned automatically. Show the QR ticket at the desk.</p></li></ol></div></section>

<section class="lp-cta"><img class="lp-bandq-img" src="<?= e($photo('cta', 'g6.svg')) ?>" alt="" loading="lazy" data-parallax2><div class="lp-bandq-shade"></div>
  <div class="container center reveal"><span class="lp-eyebrow">Experience the coast</span><h2>Rest. Recharge. <em>Repeat.</em></h2><a class="btn btn-fill btn-lg" href="#search">Reserve now</a></div></section>

<section class="section lp-mapsec" id="map"><div class="container"><div class="center"><div class="eyebrow reveal">Find us</div><h2 class="reveal">Around <em>Puerto Galera</em></h2>
  <p class="mute reveal" style="max-width:640px;margin:0 auto">From the town proper to the beaches and dive spots, pick a place to see it on the map.</p></div>
  <div class="lp-map reveal">
    <div class="lp-map-side">
      <div class="label" style="margin-bottom:8px">Our hotels</div>
      <?php foreach ($places as $i => $pl): ?><button type="button" class="lp-place<?= $i === 0 ? ' on' : '' ?>" data-map="<?= e($osm((float) $pl['lat'], (float) $pl['lng'], .012)) ?>"><b><?= e($pl['name']) ?></b><span><?= e($pl['area'] ?? '') ?></span></button><?php endforeach; ?>
      <button type="button" class="lp-place" data-map="<?= e($osm(13.5, 120.954, .075)) ?>"><b>All of Puerto Galera</b><span>The whole municipality</span></button>
      <div class="label" style="margin:16px 0 8px">Explore an area</div>
      <div class="chips"><?php foreach ((array) config_item('hotelgo_areas') as $a): ?><a class="chip" target="_blank" rel="noopener" href="https://www.openstreetmap.org/search?query=<?= e(rawurlencode($a . ', Puerto Galera')) ?>"><?= e($a) ?> ↗</a><?php endforeach; ?></div>
    </div>
    <div class="lp-map-frame"><iframe id="lp-map-frame" title="Map of Puerto Galera" loading="lazy" referrerpolicy="no-referrer" src="<?= e($osm($lat, $lng, .012)) ?>"></iframe></div>
  </div></div></section>

<section class="section" id="gallery" style="padding-top:20px"><div class="container"><div class="center"><div class="eyebrow reveal">Visual stories</div><h2 class="reveal">Our <em>Gallery</em></h2></div>
  <div class="lp-gallery"><?php foreach ($gallery as $i => [$slot, $f, $alt, $cap]): $u = $photo($slot, $f); ?>
    <button type="button" class="lp-g g<?= $i + 1 ?> reveal" data-full="<?= e($u) ?>" data-alt="<?= e($alt) ?>"><img src="<?= e($u) ?>" alt="<?= e($alt) ?>" loading="lazy"><span><?= e($cap) ?></span></button>
  <?php endforeach; ?></div></div></section>

<section class="section lp-voices" id="voices"><div class="container"><div class="center"><div class="eyebrow reveal">Guest voices</div><h2 class="reveal">Memories that <em>last</em></h2></div>
  <div class="lp-slider reveal" data-slider>
    <?php foreach ($voices as $i => [$q, $who]): ?><figure class="lp-voice<?= $i === 0 ? ' on' : '' ?>"><blockquote>“<?= e($q) ?>”</blockquote><figcaption><?= e($who) ?></figcaption></figure><?php endforeach; ?>
    <div class="lp-dots" role="tablist" aria-label="Guest voices"><?php foreach ($voices as $i => $_): ?><button type="button" class="<?= $i === 0 ? 'on' : '' ?>" aria-label="Show comment <?= $i + 1 ?>"></button><?php endforeach; ?></div>
  </div>
  <p class="small mute center">Sample comments for demonstration. They are replaced by real guest reviews as guests stay with us.</p></div></section>

<section class="section" id="help" style="padding-top:20px"><div class="container grid g2" style="align-items:start">
  <div class="reveal"><div class="eyebrow">Help</div><h2>Questions, <em>answered</em></h2><p class="mute">Cannot find yours? Write to us and we will reply.</p>
    <p><a class="btn btn-out" href="mailto:<?= e($mail) ?>">Email <?= e($mail) ?></a></p>
    <p class="small"><a href="<?= e(site_url('terms')) ?>">Terms</a> · <a href="<?= e(site_url('privacy')) ?>">Privacy</a> · <a href="<?= e(site_url('cancellation-policy')) ?>">Cancellation &amp; refunds</a> · <a href="<?= e(site_url('house-rules')) ?>">House rules</a></p></div>
  <div class="reveal d1"><?php foreach ($faq as [$q, $a]): ?><details class="lp-faq"><summary><?= e($q) ?></summary><p><?= e($a) ?></p></details><?php endforeach; ?></div>
</div></section>

<section class="section lp-contact" id="contact"><div class="container grid g2" style="align-items:start">
  <div class="reveal"><div class="eyebrow" style="color:#F5A524">Get in touch</div><h2>We&rsquo;d love to <em>hear from you</em></h2>
    <ul class="lp-info">
      <li><span class="ico2"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#F5A524" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M3 6h18v12H3zM3 7l9 7 9-7"/></svg></span><div><small>Email</small><a href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a></div></li>
      <li><span class="ico2"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#F5A524" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M5 4h4l2 5-2 1a11 11 0 005 5l1-2 5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/></svg></span><div><small>Phone</small><a href="tel:<?= e($tel) ?>"><?= e($tel) ?></a></div></li>
      <li><span class="ico2"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#F5A524" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M12 22s7-6.5 7-12a7 7 0 10-14 0c0 5.5 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg></span><div><small>Location</small><span><?= e($loc) ?></span></div></li>
    </ul></div>
  <form class="lp-form reveal d1" data-mailto="<?= e($mail) ?>" novalidate>
    <div class="grid g2" style="gap:0 14px"><div class="field"><label for="cf-name">Your name</label><input id="cf-name" name="name" autocomplete="name" placeholder="Juan Dela Cruz" required></div>
    <div class="field"><label for="cf-mail">Your email</label><input id="cf-mail" name="email" type="email" autocomplete="email" placeholder="juan@example.com" required></div></div>
    <div class="field"><label for="cf-sub">Subject</label><select id="cf-sub" name="subject"><option>Room reservation</option><option>Payment question</option><option>Cancellation or refund</option><option>Something else</option></select></div>
    <div class="field"><label for="cf-msg">Message</label><textarea id="cf-msg" name="message" placeholder="How can we help you?" required></textarea></div>
    <button class="btn btn-fill btn-block" type="submit">Send message</button>
    <p class="small" data-mailto-note style="margin:10px 0 0">This opens your email app with the message ready to send.</p>
  </form>
</div></section>

<div class="lp-lightbox" id="lightbox" hidden role="dialog" aria-modal="true" aria-label="Photo viewer"><button type="button" class="lb-x" aria-label="Close">×</button><button type="button" class="lb-n lb-prev" aria-label="Previous">‹</button><img alt=""><button type="button" class="lb-n lb-next" aria-label="Next">›</button><p></p></div>
