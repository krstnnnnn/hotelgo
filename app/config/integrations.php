<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| The five integrations (payment, SMS, map, email, calendar)
|--------------------------------------------------------------------------
| Secrets are never written here. They are read from .env (your computer) or
| Render > Environment. Empty value = safe "simulation / demo" mode.
| Read anywhere with config_item('payment_provider') and so on.
*/

/* 1. Online payment gateway: mock (sandbox page) or paymongo (use TEST keys first) */
$config['payment_provider']        = getenv('PAYMENT_PROVIDER') ?: 'mock';
$config['paymongo_public_key']     = (string) (getenv('PAYMONGO_PUBLIC_KEY') ?: getenv('PAYMENT_PUBLIC_KEY'));
$config['paymongo_secret_key']     = (string) (getenv('PAYMONGO_SECRET_KEY') ?: getenv('PAYMENT_SECRET_KEY'));
$config['paymongo_methods']        = getenv('PAYMONGO_METHODS') ?: 'gcash,paymaya,grab_pay,shopeepay';   /* e-wallets to offer; card / bank always go through the payment link */
$config['paymongo_api']            = 'https://api.paymongo.com/v1';

/* 2. SMS notification service (optional and paid): log (demo) or semaphore */
$config['sms_provider']            = getenv('SMS_PROVIDER') ?: 'log';
$config['sms_api_key']             = (string) getenv('SMS_API_KEY');
$config['sms_sender']              = (string) getenv('SMS_SENDER');
$config['sms_otp_route']           = getenv('SMS_OTP_ROUTE') ?: 'messages';

/* 3. Map: free OpenStreetMap; a Google key switches to the Google embed */
$config['google_maps_key']         = (string) getenv('GOOGLE_MAPS_KEY');

/* 4. Email: log (demo), appsscript (free Gmail relay) or resend */
$config['email_provider']          = getenv('EMAIL_PROVIDER') ?: 'log';
$config['apps_script_url']         = (string) getenv('APPS_SCRIPT_URL');
$config['apps_script_secret']      = (string) getenv('APPS_SCRIPT_SECRET');
$config['resend_api_key']          = (string) getenv('RESEND_API_KEY');
$config['mail_from']               = (string) getenv('MAIL_FROM');
$config['mail_from_name']          = getenv('MAIL_FROM_NAME') ?: 'HOTELGO';

/* 5. Calendar: guests always get the built-in .ics file. Google Calendar sync is real (Calendar API v3, service account) and optional. */
$config['google_calendar_json']    = (string) getenv('GOOGLE_SERVICE_ACCOUNT_JSON');            /* the whole key file of the service account (raw JSON or base64) */
$config['google_calendar_id']      = (string) getenv('GOOGLE_CALENDAR_ID');                      /* calendar used by a hotel that has no calendar of its own */
$config['google_calendar_api']     = 'https://www.googleapis.com/calendar/v3';
$config['google_token_url']        = 'https://oauth2.googleapis.com/token';

/* Only needed on some Windows computers: path to cacert.pem */
$config['curl_ca_bundle']          = (string) getenv('CURL_CA_BUNDLE');
