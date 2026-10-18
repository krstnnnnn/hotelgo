<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<div class="container section"><div class="card" style="max-width:460px;margin:0 auto">
  <h1 style="font-size:2rem">Set a new password</h1>
  <form method="post" action="<?= e(site_url('reset')) ?>"><?= csrf_input() ?>
    <div class="field"><label for="code">6-digit code</label><input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code"></div>
    <div class="field"><label for="password">New password</label><input id="password" name="password" type="password" required minlength="8" maxlength="72" autocomplete="new-password"><div class="help">8+ characters with letters and numbers.</div></div>
    <div class="field"><label for="password2">Confirm new password</label><input id="password2" name="password2" type="password" required maxlength="72" autocomplete="new-password"></div>
    <button class="btn btn-block" type="submit">Save password</button></form>
</div></div>
