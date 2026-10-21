<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<div class="container section"><div class="card" style="max-width:460px;margin:0 auto">
  <div class="eyebrow">Step 2 of 2</div><h1 style="font-size:2rem">Verify your email</h1><p class="mute">Enter the 6-digit code we sent to your <b><?= e(mask_email($email ?? '')) ?></b>. It expires in 10 minutes. (Check your spam folder too.)</p>
  <form method="post" action="<?= e(site_url('verify')) ?>"><?= csrf_input() ?>
    <div class="field"><label for="code">6-digit code</label><input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code"></div>
    <button class="btn btn-block" type="submit">Verify and continue</button></form>
  <form method="post" action="<?= e(site_url('verify/resend')) ?>" style="margin-top:12px"><?= csrf_input() ?><button class="btn btn-out btn-block" type="submit">Send a new code</button></form>
</div></div>
