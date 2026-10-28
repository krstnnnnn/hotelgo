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
    }

    /* Returns a list of [name, connected (true/false), note] */
    public function status(): array
    {
        $pay  = $this->_lava->paymongo;
        $mail = $this->_lava->messenger;
        $paymongo = config_item('payment_provider') === 'paymongo';
        $emailProvider = config_item('email_provider');

        return [
            ['Online payment gateway',
                $paymongo ? $pay->key_mode() !== 'invalid' : $pay->mock_allowed(),
                $paymongo ? 'PayMongo · ' . strtoupper($pay->key_mode()) . ' key · public key ' . ($pay->public_key() !== '' ? 'set' : 'not set (optional)') . ' · e-wallets: ' . implode(', ', $pay->wallets()) . ' · payment link for card / bank' : 'Sandbox (simulation)'],
            ['SMS notification service (optional)',
                $mail->sms_enabled(),
                config_item('sms_provider') === 'semaphore' ? 'Semaphore · real SMS (paid)' : 'Demo only: SMS messages are saved in Notifications, not sent (real SMS needs a paid provider)'],
            ['Google Maps',
                true,
                config_item('google_maps_key') !== '' ? 'Google Maps embed + directions' : 'Free map (OpenStreetMap) + "Get directions" opens Google Maps (no key needed)'],
            ['Email notification service',
                $mail->email_enabled(),
                $emailProvider === 'appsscript' ? 'Free Gmail relay (Google Apps Script) · sign-up codes are emailed'
                    : ($emailProvider === 'resend' ? 'Resend · from: ' . (config_item('mail_from') ?: 'MAIL_FROM is missing') : 'Demo only: emails are saved in Notifications, not sent')],
            ['Calendar (.ics file)', true, 'Built in – guests download a calendar file'],
        ];
    }
}
