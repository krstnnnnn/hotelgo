<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); $v = fn($k, $d = '') => e($edit[$k] ?? $d); ?>
<h1>Rooms &amp; rates</h1>
<div class="grid" style="grid-template-columns:1.4fr 1fr;align-items:start">
<div class="stack">
  <div class="card scroll"><h3>Room types</h3><table class="table"><thead><tr><th>Type</th><th>3 h</th><th>6 h</th><th>12 h</th><th>Overnight</th><th></th></tr></thead><tbody>
  <?php foreach ($types as $t): ?><tr><td><b><?= e($t['name']) ?></b> <?= (int) $t['is_active'] ? '' : '<span class="pill gray">hidden</span>' ?><div class="small mute">max <?= (int) $t['max_guests'] ?> · buffer <?= (int) $t['cleaning_buffer_min'] ?> min</div></td>
    <td><?= e(money($t['price_3h'])) ?></td><td><?= e(money($t['price_6h'])) ?></td><td><?= e(money($t['price_12h'])) ?></td><td><?= e(money($t['price_overnight'])) ?></td><td><a class="btn btn-sm btn-out" href="<?= e(site_url('admin/rooms')) ?>?edit=<?= (int) $t['id'] ?>">Edit</a></td></tr><?php endforeach; ?></tbody></table></div>
  <div class="card scroll"><h3>Rooms</h3><table class="table"><thead><tr><th>Room</th><th>Type</th><th>Floor</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($rooms as $r): ?><tr><td><b><?= e($r['room_no']) ?></b></td><td><?= e($r['type_name']) ?></td><td><?= (int) $r['floor'] ?></td><td><span class="pill <?= e(status_class($r['status'])) ?>"><?= e($r['status']) ?></span></td>
    <td><form method="post" class="inline" action="<?= e(site_url('admin/rooms/' . (int) $r['id'] . '/status')) ?>"><?= csrf_input() ?><input type="hidden" name="status" value="<?= $r['status'] === 'active' ? 'maintenance' : 'active' ?>"><button class="btn btn-sm btn-out" type="submit"><?= $r['status'] === 'active' ? 'Set maintenance' : 'Make bookable' ?></button></form></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="stack"><div class="card"><h3><?= $edit ? 'Edit room type' : 'Add room type' ?></h3>
  <form method="post" action="<?= e(site_url('admin/rooms/type')) ?>"><?= csrf_input() ?><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <div class="field"><label for="name">Name</label><input id="name" name="name" required maxlength="80" value="<?= $v('name') ?>"></div>
    <div class="field"><label for="description">Description</label><textarea id="description" name="description" maxlength="500"><?= $v('description') ?></textarea></div>
    <div class="field"><label for="amenities">Amenities (comma separated)</label><input id="amenities" name="amenities" maxlength="250" value="<?= $v('amenities') ?>"></div>
    <div class="grid g2"><div class="field"><label for="max_guests">Max guests</label><input id="max_guests" type="number" min="1" max="20" name="max_guests" value="<?= $v('max_guests', 2) ?>" required></div>
    <div class="field"><label for="cleaning_buffer_min">Cleaning buffer (min)</label><input id="cleaning_buffer_min" type="number" min="0" max="180" name="cleaning_buffer_min" value="<?= $v('cleaning_buffer_min', 15) ?>" required></div>
    <?php foreach (['price_3h' => '3 hours', 'price_6h' => '6 hours', 'price_12h' => '12 hours', 'price_overnight' => 'Overnight'] as $k => $n): ?><div class="field"><label for="<?= e($k) ?>">Price · <?= e($n) ?></label><input id="<?= e($k) ?>" name="<?= e($k) ?>" type="number" step="0.01" min="0" max="100000" required value="<?= $v($k) ?>"></div><?php endforeach; ?></div>
    <div class="check"><input id="is_active" type="checkbox" name="is_active" value="1"<?= ($edit['is_active'] ?? 1) ? ' checked' : '' ?>><label for="is_active">Visible to guests</label></div>
    <button class="btn btn-block" type="submit">Save changes</button></form></div>
  <div class="card"><h3>Add a room</h3><form method="post" action="<?= e(site_url('admin/rooms/add')) ?>"><?= csrf_input() ?>
    <div class="grid g2"><div class="field"><label for="room_no">Room number</label><input id="room_no" name="room_no" required maxlength="10"></div><div class="field"><label for="floor">Floor</label><input id="floor" name="floor" type="number" min="1" max="50" value="1" required></div></div>
    <div class="field"><label for="type">Room type</label><select id="type" name="type"><?php foreach ($types as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></div><button class="btn btn-ink btn-block" type="submit">Add room</button></form></div></div></div>
