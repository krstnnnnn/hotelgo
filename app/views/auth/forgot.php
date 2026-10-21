<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<div class="container section"><div class="card" style="max-width:460px;margin:0 auto">
  <h1 style="font-size:2rem">Forgot your password?</h1><p class="mute">Enter your account email. We will email you a 6-digit reset code.</p>
  <form method="post" action="<?= e(site_url('forgot')) ?>"><?= csrf_input() ?>
    <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required maxlength="150"></div>
    <button class="btn btn-block" type="submit">Send reset code</button></form>
  <p class="center small" style="margin-top:16px"><a href="<?= e(site_url('login')) ?>">← Back to sign in</a></p>
</div></div>
