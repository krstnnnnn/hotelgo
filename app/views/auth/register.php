<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<div class="container section"><div class="card" style="max-width:520px;margin:0 auto">
  <div class="eyebrow">Step 1 of 2 · Your details</div><h1 style="font-size:2rem">Create your account</h1><p class="mute">We will text you a 6-digit code to verify your mobile number.</p>
  <form method="post" action="<?= e(site_url('register')) ?>" autocomplete="on"><?= csrf_input() ?>
    <div class="field"><label for="full_name">Full name</label><input id="full_name" name="full_name" required maxlength="120" value="<?= e($old['full_name'] ?? '') ?>" autocomplete="name"></div>
    <div class="field"><label for="mobile">Mobile number</label><input id="mobile" name="mobile" required maxlength="20" placeholder="09XX XXX XXXX" value="<?= e($old['mobile'] ?? '') ?>" autocomplete="tel"></div>
    <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required maxlength="150" value="<?= e($old['email'] ?? '') ?>" autocomplete="email"></div>
    <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required minlength="8" maxlength="72" autocomplete="new-password"><div class="help">8+ characters with at least one letter and one number.</div></div>
    <div class="field"><label for="password2">Confirm password</label><input id="password2" name="password2" type="password" required maxlength="72" autocomplete="new-password"></div>
    <div class="check"><input id="agree" type="checkbox" name="agree" value="1" required><label for="agree">I agree to the house rules and privacy notice.</label></div>
    <button class="btn btn-block" type="submit">Create account</button></form>
  <p class="center small" style="margin-top:16px">Already have an account? <a href="<?= e(site_url('login')) ?>"><b>Sign in</b></a></p>
</div></div>
