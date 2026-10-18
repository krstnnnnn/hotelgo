<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<h1>Guest records</h1><p class="mute">Personal data. Every time you open a guest, it is written to the audit log.</p>
<form method="get" action="<?= e(site_url('admin/guests')) ?>" class="row" style="margin:12px 0"><input name="q" placeholder="Search name or exact email" maxlength="80" value="<?= e($search) ?>" style="max-width:320px"><button class="btn btn-ink" type="submit">Search</button></form>
<div class="card scroll"><table class="table"><thead><tr><th>Guest</th><th>Mobile</th><th>Stays</th><th>Last visit</th></tr></thead><tbody>
<?php foreach ($rows as $g): ?><tr><td><a href="<?= e(site_url('admin/guests/' . (int) $g['id'])) ?>"><b><?= e($g['full_name']) ?></b></a><div class="small mute"><?= e(mask_email($g['email'])) ?></div></td><td><?= e(mask_mobile($g['mobile'])) ?></td><td><?= (int) $g['stays'] ?></td><td><?= e($g['last_visit'] ? fmt_dt($g['last_visit'], 'j M Y') : '—') ?></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="4" class="center mute">No guests found.</td></tr><?php endif; ?></tbody></table></div>
