<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<section class="hero"><div class="container grid g2" style="align-items:center">
  <div><span class="pill" style="background:#14344A;color:#F5A524">Puerto Galera · Oriental Mindoro</span>
    <h1 style="margin-top:16px">Book a room <em>by the hour</em> or overnight.</h1>
    <p>Choose your time, pay online, and we assign a free room right away. Confirmation lands on your phone and inbox.</p>
    <div class="row"><a class="btn btn-amber" href="<?= e(site_url('rooms')) ?>">Find a room</a><a class="btn btn-out" href="#how">See how it works →</a></div></div>
  <div class="art" aria-hidden="true"><div class="sun"></div><div class="wave"></div><div class="wave w2"></div><div class="wave w3"></div>
    <span class="tag" style="left:20px;top:20px"><b>Room assigned</b><br>Deluxe · 3 hrs · 7:00 PM</span><span class="tag" style="right:20px;bottom:24px">● Live availability</span></div>
</div></section>
<section class="search"><div class="container"><div class="card"><?= search_form(['stay' => 'hourly', 'date' => date('Y-m-d'), 'time' => '19:00', 'hours' => 3, 'guests' => 2]) ?></div></div></section>
<section class="section" id="how"><div class="container"><div class="eyebrow">How it works</div><h2>Booked in three steps. No front-desk wait.</h2>
  <div class="grid g3"><div class="card"><h3>1 · Pick your time</h3><p class="mute">Choose hourly or overnight, then your date, start time and guests.</p></div>
  <div class="card"><h3>2 · Pay online, securely</h3><p class="mute">Pay by e-wallet, card or bank transfer. Your receipt is sent right away.</p></div>
  <div class="card"><h3>3 · Get your room</h3><p class="mute">A free room is assigned automatically. Your SMS has the room number.</p></div></div></div></section>
<section class="section" style="padding-top:0"><div class="container"><div class="eyebrow">Popular rooms</div><h2>Comfortable rooms near the beach.</h2>
  <div class="grid g4"><?php foreach ($types as $i => $t): ?>
    <div class="card flat"><div class="scene t<?= ($i % 3) + 1 ?>"><i></i></div><div style="padding:18px"><h3><?= e($t['name']) ?></h3><div class="chips"><?php foreach (array_slice(explode(',', (string) $t['amenities']), 0, 3) as $a): ?><span class="chip"><?= e(trim($a)) ?></span><?php endforeach; ?></div>
      <div class="row between" style="margin-top:14px"><div><div class="small mute">3 hours from</div><div class="price"><?= e(money($t['price_3h'])) ?></div></div><div class="right"><div class="small mute">Overnight</div><b><?= e(money($t['price_overnight'])) ?></b></div></div></div></div>
  <?php endforeach; ?></div></div></section>
