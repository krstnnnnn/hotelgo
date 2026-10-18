<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<div class="<?= in_array($_user['role'], ['staff', 'admin'], true) ? '' : 'container section' ?>"><div class="card" style="max-width:460px;margin:0 auto">
  <h1 style="font-size:2rem">Change password</h1>
  <?php if ((int) $_user['must_change_password'] === 1): ?><div class="flash warn">You are using a temporary password. Please choose your own now.</div><?php endif; ?>
  <form method="post" action="<?= e(site_url('account/password')) ?>"><?= csrf_input() ?>
    <div class="field"><label for="current">Current password</label><input id="current" name="current" type="password" required maxlength="72" autocomplete="current-password"></div>
    <div class="field"><label for="password">New password</label><input id="password" name="password" type="password" required minlength="8" maxlength="72" autocomplete="new-password"></div>
    <div class="field"><label for="password2">Confirm new password</label><input id="password2" name="password2" type="password" required maxlength="72" autocomplete="new-password"></div>
    <button class="btn btn-block" type="submit">Update password</button></form>
</div></div>
