<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Email providers.
|   email_provider=appsscript -> FREE: your own Gmail through a small Google Apps Script web app (works on Render free, no domain needed)
|   email_provider=resend     -> Resend (free plan, but you need your own verified domain to email other people)
|   anything else             -> nothing is sent. The caller saves the message as "logged" (not sent).
| All settings come from app/config/integrations.php (config_item).
| Load it with:  $this->call->library('messenger');   then:  $this->messenger->deliver(...)
| A failed email NEVER stops a booking: deliver() returns the reason instead of throwing.
*/
class Messenger
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->library('http_client');
    }

    public function email_enabled(): bool
    {
        $p = config_item('email_provider');
        return ($p === 'appsscript' && config_item('apps_script_url') !== '' && config_item('apps_script_secret') !== '')
            || ($p === 'resend' && config_item('resend_api_key') !== '' && config_item('mail_from') !== '');
    }

    /* =============== Google Apps Script relay (free email from your own Gmail) =============== */
    public function appsscript_request(string $to, string $subject, string $text, string $html = '', array $attachments = []): array
    {
        $files = [];
        foreach ($attachments as $a) $files[] = ['filename' => $a['filename'], 'mime' => $a['mime'] ?? 'application/octet-stream', 'content' => $a['content']];
        $payload = ['secret' => config_item('apps_script_secret'), 'to' => $to, 'subject' => $subject, 'text' => $text, 'html' => $html, 'attachments' => $files, 'fromName' => config_item('mail_from_name')];
        return ['url' => config_item('apps_script_url'), 'headers' => ['Content-Type: application/json'], 'body' => json_encode($payload)];
    }
    public function appsscript_parse(int $http, string $body): array
    {
        $j = json_decode($body, true);
        if (is_array($j) && !empty($j['ok'])) return ['sent', null, null];
        if (is_array($j)) return ['failed', null, mb_substr('Gmail relay: ' . ($j['error'] ?? 'unknown error'), 0, 240)];
        return ['failed', null, 'The Google Apps Script did not answer correctly (HTTP ' . $http . '). Check APPS_SCRIPT_URL and that "Who has access" is set to Anyone.'];
    }

    /* =============== Resend (email) =============== */
    public function resend_request(string $to, string $subject, string $text, string $html = '', array $attachments = []): array
    {
        $from = config_item('mail_from');
        if ($from !== '' && !str_contains($from, '<')) $from = config_item('mail_from_name') . ' <' . $from . '>';
        $payload = ['from' => $from, 'to' => [$to], 'subject' => $subject, 'text' => $text];
        if ($html !== '') $payload['html'] = $html;
        if ($attachments) $payload['attachments'] = $attachments;
        return ['url' => 'https://api.resend.com/emails', 'headers' => ['Authorization: Bearer ' . config_item('resend_api_key'), 'Content-Type: application/json', 'Accept: application/json'], 'body' => json_encode($payload)];
    }
    public function resend_parse(int $http, string $body): array
    {
        $j = json_decode($body, true);
        if ($http >= 200 && $http < 300 && !empty($j['id'])) return ['sent', (string) $j['id'], null];
        $msg = is_array($j) ? (($j['message'] ?? '') ?: json_encode($j)) : $body;
        return ['failed', null, mb_substr('Resend error (HTTP ' . $http . '): ' . trim($msg), 0, 240)];
    }

    /* =============== send one message. Returns [status, provider ref, error] =============== */
    public function deliver(string $channel, string $to, string $subject, string $message, array $opts = []): array
    {
        $status = 'logged'; $error = null; $ref = null; $req = null; $parse = null; $follow = false;
        try {
            if ($channel === 'email' && config_item('email_provider') === 'appsscript' && config_item('apps_script_url') !== '' && config_item('apps_script_secret') !== '') {
                if (!str_starts_with(config_item('apps_script_url'), 'https://script.google.com/')) throw new RuntimeException('APPS_SCRIPT_URL must start with https://script.google.com/');
                $req = $this->appsscript_request($to, $subject, $message, $opts['html'] ?? '', $opts['attachments'] ?? []); $parse = 'appsscript_parse'; $follow = true;
            } elseif ($channel === 'email' && config_item('email_provider') === 'resend' && config_item('resend_api_key') !== '') {
                if (config_item('mail_from') === '') throw new RuntimeException('MAIL_FROM is not set (for testing you can use onboarding@resend.dev).');
                $req = $this->resend_request($to, $subject, $message, $opts['html'] ?? '', $opts['attachments'] ?? []); $parse = 'resend_parse';
            }
            if ($req) {
                [$http, $res, $err] = $this->_lava->http_client->request('POST', $req['url'], $req['headers'], $req['body'], 25, $follow);
                if ($err !== '') {
                    $status = 'failed';
                    $error  = mb_substr($err . (stripos($err, 'certificate') !== false ? ' (set CURL_CA_BUNDLE, see the guide)' : ''), 0, 240);
                } else {
                    [$status, $ref, $error] = $this->$parse($http, $res);
                }
            }
        } catch (Throwable $e) {
            $status = 'failed'; $error = mb_substr($e->getMessage(), 0, 240);
        }
        return [$status, $ref, $error];
    }
}
