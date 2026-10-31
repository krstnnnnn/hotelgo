<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Shows which of the five integrations are connected (payment, SMS, map, email, calendar).
| Used by the owner's Settings page and by the API. It only reads settings, it never sends anything.
| Load it with:  $this->call->library('integrations');   then:  $this->integrations->status()
*/
class Integrations
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->library('paymongo');
        $this->_lava->call->library('messenger');
        $this->_lava->call->library('google_calendar');
    }

    /* Returns a list of [name, connected (true/false), note] */
    public function status(): array
    {
        $pay  = $this->_lava->paymongo;
        $mail = $this->_lava->messenger;
        $emailProvider = config_item('email_provider');

        return [
            ['Online payment gateway',
                $pay->configured(),
                $pay->configured() ? 'PayMongo · ' . strtoupper($pay->key_mode()) . ' key · public key ' . ($pay->public_key() !== '' ? 'set' : 'not set (optional)') . ' · e-wallets: ' . implode(', ', $pay->wallets()) . ' · payment link for card / bank' : 'Not connected: set PAYMONGO_SECRET_KEY. Guests cannot pay online until it is set.'],
            ['SMS notification service (optional)',
                $mail->sms_enabled(),
                config_item('sms_provider') === 'semaphore' ? 'Semaphore · real SMS (paid)' : 'Off: SMS messages are not sent (a paid provider is needed). Email still works.'],
            ['Google Maps',
                true,
                config_item('google_maps_key') !== '' ? 'Google Maps embed + directions' : 'Free map (OpenStreetMap) + "Get directions" opens Google Maps (no key needed)'],
            ['Email notification service',
                $mail->email_enabled(),
                $emailProvider === 'appsscript' ? 'Free Gmail relay (Google Apps Script) · sign-up codes are emailed'
                    : ($emailProvider === 'resend' ? 'Resend · from: ' . (config_item('mail_from') ?: 'MAIL_FROM is missing') : 'Not connected: set EMAIL_PROVIDER and its keys. Sign-up codes cannot be sent until it is set.')],
            ['Google Calendar',
                $this->_lava->google_calendar->configured(),
                $this->_lava->google_calendar->configured()
                    ? 'Google Calendar API · service account ' . $this->_lava->google_calendar->email() . ' · every paid booking is added to the hotel calendar · guests also get the built-in .ics file'
                    : 'Not connected: set GOOGLE_SERVICE_ACCOUNT_JSON. Guests still get the built-in .ics calendar file.'],
        ];
    }
}
