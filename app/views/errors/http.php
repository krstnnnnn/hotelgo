<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<div class="container section center"><div class="eyebrow">Error <?= (int) $code ?></div><h1>Oops.</h1><p class="mute"><?= e($message) ?></p><a class="btn" href="<?= e(site_url('')) ?>">Back to home</a></div>
