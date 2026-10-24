<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| QR code library settings (app/libraries/Qr.php)
|--------------------------------------------------------------------------
*/
$config['qr_color']       = '#0B2233';   // dark squares (the HOTELGO ink colour)
$config['qr_quiet_zone']  = 2;           // empty border around the code, in squares
$config['qr_error_level'] = 'M';         // L (7%), M (15%), Q (25%) or H (30%) damage can be repaired
