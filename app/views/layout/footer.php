<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
</main>
<footer><div class="container grid lp-foot">
  <div><div class="logo" style="color:#fff"><span>HOTEL<b style="color:#F5A524">GO</b></span></div><p class="small">Hourly and overnight motel booking for Puerto Galera, Oriental Mindoro.</p>
    <p class="small"><a href="mailto:<?= e(config_item('hotelgo_contact_email')) ?>"><?= e(config_item('hotelgo_contact_email')) ?></a><a href="tel:<?= e(config_item('hotelgo_contact_phone')) ?>"><?= e(config_item('hotelgo_contact_phone')) ?></a><span><?= e(config_item('hotelgo_contact_location')) ?></span></p></div>
  <div><h4>Guests</h4><a href="<?= e(site_url('rooms')) ?>">Search rooms</a><a href="<?= e(site_url('my-bookings')) ?>">My bookings</a><a href="<?= e(site_url('')) ?>#help">Help &amp; FAQ</a><a href="<?= e(site_url('')) ?>#contact">Contact us</a></div>
  <div><h4>Legal</h4><a href="<?= e(site_url('terms')) ?>">Terms of Service</a><a href="<?= e(site_url('privacy')) ?>">Privacy Policy</a><a href="<?= e(site_url('cancellation-policy')) ?>">Cancellation &amp; refunds</a><a href="<?= e(site_url('house-rules')) ?>">House rules</a></div>
  <div><h4>Motel staff</h4><a href="<?= e(site_url('admin/login')) ?>">Staff / Admin sign in</a></div>
</div><div class="container small" style="margin-top:24px;border-top:1px solid #14344A;padding-top:16px">© <?= date('Y') ?> HOTELGO · Puerto Galera, Oriental Mindoro, Philippines</div></footer>
<script src="<?= e(base_url('assets/js/app.js')) ?>" defer></script>
</body></html>
