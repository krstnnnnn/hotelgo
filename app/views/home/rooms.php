<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<div class="container section" style="padding-top:28px">
  <div class="card" style="margin-bottom:20px"><?= search_form($p) ?></div>
  <?php if ($error): ?><div class="flash bad"><?= e($error) ?></div><?php else: ?>
  <h2><?= count($rows) ?> room type<?= count($rows) === 1 ? '' : 's' ?> for <?= e(fmt_dt($w['start'], 'D j M, g:i A')) ?> – <?= e(fmt_dt($w['end'], 'D j M, g:i A')) ?></h2>
  <div class="flash info">You choose a room <b>type</b>. The exact room is <b>assigned automatically</b> and sent to you by SMS and email.</div>
  <div class="stack" style="margin-top:20px">
  <?php foreach ($rows as $i => $t): ?>
    <div class="result<?= $t['free'] < 1 ? ' full' : '' ?>"><?php $pu = photo_url($t['image_id'] ?? 0) ?: photo_url($t['hotel_image_id'] ?? 0); ?>
      <?php if ($pu !== ''): ?><div class="scene photo"><img src="<?= e($pu) ?>" alt="<?= e($t['name']) ?>" loading="lazy"></div><?php else: ?><div class="scene t<?= ($i % 3) + 1 ?>"><i></i></div><?php endif; ?>
      <div class="body"><div class="row"><h3 style="margin:0"><?= e($t['name']) ?></h3><?php if (!empty($t['is_vip'])): ?><span class="pill vip"><?= icon('star', '#7A4B00', 13) ?> VIP</span><?php endif; ?><span class="pill info"><?= e($t['hotel_name'] ?? '') ?><?= ($t['hotel_area'] ?? '') !== '' ? ' · ' . e($t['hotel_area']) : '' ?></span>
        <?php if ($t['free'] < 1): ?><span class="pill gray">Fully booked</span><?php elseif ($t['free'] <= 2): ?><span class="pill warn">Only <?= (int) $t['free'] ?> left</span><?php else: ?><span class="pill ok"><?= (int) $t['free'] ?> rooms open</span><?php endif; ?></div>
        <p class="mute"><?= e($t['description']) ?></p><?php if (!empty($t['is_vip'])): ?><ul class="vipinc"><?php foreach (included_list($t) as [$it, $id]): ?><li><b><?= e($it) ?></b><?= $id !== '' ? ' · ' . e($id) : '' ?></li><?php endforeach; ?></ul><?php endif; ?><div class="chips"><?php foreach (explode(',', (string) $t['amenities']) as $a): ?><span class="chip"><?= e(trim($a)) ?></span><?php endforeach; ?><span class="chip">Max <?= (int) $t['max_guests'] ?> guests</span></div>
        <p class="small mute" style="margin:10px 0 0">Free cancellation until <?= (int) setting('cancel_hours', '2') ?> hours before check-in</p></div>
      <div class="buy"><div class="price"><?= e(money($t['price'])) ?></div><div class="small mute"><?= $p['stay'] === 'overnight' ? 'overnight' : (int) $w['hours'] . ' hrs' ?> · fee added at checkout</div>
        <?php if ($t['free'] > 0): ?><a class="btn" style="margin-top:12px" href="<?= e($t['link']) ?>">Select room</a><?php else: ?><span class="small mute">Try another time</span><?php endif; ?></div></div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="card center">No room type fits that many guests. Try fewer guests.</div><?php endif; ?></div>
  <?php endif; ?>
</div>
