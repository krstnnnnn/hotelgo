<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
</main>
<footer><div class="container grid g3">
  <div><div class="logo" style="color:#fff"><span>HOTEL<b style="color:#F5A524">GO</b></span></div><p class="small">Hourly and overnight motel booking for Puerto Galera, Oriental Mindoro.</p></div>
  <div><h4>Guests</h4><a href="<?= e(site_url('rooms')) ?>">Search rooms</a><a href="<?= e(site_url('my-bookings')) ?>">My bookings</a></div>
  <div><h4>Motel staff</h4><a href="<?= e(site_url('admin/login')) ?>">Staff / Admin sign in</a></div>
</div><div class="container small" style="margin-top:20px">© <?= date('Y') ?> HOTELGO · Puerto Galera, Oriental Mindoro</div></footer>
<script src="<?= e(base_url('assets/js/app.js')) ?>" defer></script>
</body></html>
