<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<div class="auth">
  <div class="art"><span class="pill" style="background:#14344A;color:#F5A524"><?= $staff ? 'STAFF PORTAL' : 'Puerto Galera' ?></span>
    <h1><?= $staff ? 'Run the front desk from one screen.' : 'Your room, on your time.' ?></h1>
    <ul><li>✓ Live room availability and instant room assignment</li><li>✓ Secure online payment</li><li>✓ <?= $staff ? 'Every staff sign-in is recorded in the audit log' : 'Manage or cancel your bookings' ?></li></ul>
    <div class="sun" aria-hidden="true"></div><div class="wave" aria-hidden="true"></div><div class="wave w2" aria-hidden="true"></div><div class="wave w3" aria-hidden="true"></div></div>
  <div class="form"><div class="box">
    <div class="seg"><a class="btn btn-sm<?= $staff ? ' btn-out' : '' ?>" href="<?= e(site_url('login')) ?>">Guest</a> <a class="btn btn-sm<?= $staff ? '' : ' btn-out' ?>" href="<?= e(site_url('admin/login')) ?>">Staff / Admin</a></div>
    <h1 style="font-size:2rem;margin-top:16px"><?= $staff ? 'Staff sign in' : 'Welcome back' ?></h1>
    <p class="mute"><?= $staff ? 'For motel owners and front-desk staff.' : 'Sign in to manage your bookings.' ?></p>
    <form method="post" action="<?= e(site_url($staff ? 'admin/login' : 'login')) ?>" autocomplete="on"><?= csrf_input() ?>
      <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required maxlength="150" autocomplete="username"></div>
      <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required maxlength="72" autocomplete="current-password">
        <button class="btn btn-sm btn-out" type="button" data-toggle-password="password" style="margin-top:8px">Show / hide</button></div>
      <div class="row between" style="margin-bottom:18px"><span></span><a href="<?= e(site_url('forgot')) ?>">Forgot password?</a></div>
      <button class="btn btn-block" type="submit">Sign in</button></form>
    <?php if (!$staff): ?><p class="center small" style="margin-top:20px">New to HOTELGO? <a href="<?= e(site_url('register')) ?>"><b>Create an account</b></a></p><?php endif; ?>
  </div></div>
</div>
