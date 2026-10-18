<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<h1>Walk-in booking</h1><p class="mute">For guests at the front desk. The stay starts <b>now</b> and is marked as paid in cash.</p>
<div class="card" style="max-width:640px"><form method="post" action="<?= e(site_url('admin/walkin')) ?>"><?= csrf_input() ?>
<div class="grid g2"><div class="field"><label for="type">Room type</label><select id="type" name="type" required><?php foreach ($types as $t): ?><option value="<?= (int) $t['id'] ?>"<?= (int) ($old['type'] ?? 0) === (int) $t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?> (max <?= (int) $t['max_guests'] ?>)</option><?php endforeach; ?></select></div>
<div class="field"><label for="stay">Stay</label><select id="stay" name="stay"><option value="hourly">Hourly</option><option value="overnight"<?= ($old['stay'] ?? '') === 'overnight' ? ' selected' : '' ?>>Overnight</option></select></div>
<div class="field"><label for="hours">Hours (hourly only)</label><select id="hours" name="hours"><?php foreach ([3, 6, 12] as $h): ?><option value="<?= $h ?>"<?= (int) ($old['hours'] ?? 3) === $h ? ' selected' : '' ?>><?= $h ?> hours</option><?php endforeach; ?></select></div>
<div class="field"><label for="guests">Guests</label><input id="guests" name="guests" type="number" min="1" max="10" value="<?= e($old['guests'] ?? 2) ?>" required></div>
<div class="field"><label for="name">Guest name</label><input id="name" name="name" required maxlength="120" value="<?= e($old['name'] ?? '') ?>"></div>
<div class="field"><label for="mobile">Mobile number</label><input id="mobile" name="mobile" required maxlength="20" value="<?= e($old['mobile'] ?? '') ?>"></div></div>
<button class="btn" type="submit">Book and assign room</button></form></div>
