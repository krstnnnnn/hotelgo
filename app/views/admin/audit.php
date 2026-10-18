<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<h1>Audit log</h1><p class="mute">Who did what, and when. The last 300 actions. Nobody can edit or delete this log from the website.</p>
<div class="card scroll"><table class="table"><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Detail</th><th>IP address</th></tr></thead><tbody>
<?php foreach ($rows as $a): ?><tr><td><?= e(fmt_dt($a['created_at'], 'j M Y g:i A')) ?></td><td><?= e($a['full_name'] ?? 'guest / system') ?> <span class="small mute"><?= e($a['role'] ?? '') ?></span></td><td><b><?= e($a['action']) ?></b></td><td class="small"><?= e($a['detail']) ?></td><td class="small"><?= e($a['ip']) ?></td></tr><?php endforeach; ?></tbody></table></div>
