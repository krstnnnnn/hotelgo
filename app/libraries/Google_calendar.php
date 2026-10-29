<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Google Calendar API v3. Talks to Google only: it never touches our database.
| Load it with:  $this->call->library('google_calendar');   then:  $this->google_calendar->save_event($calendarId, $eventId, $event)
|
| How it signs in: a Google SERVICE ACCOUNT (a robot account). Nobody has to log in with Google.
|   1. Google Cloud Console > create a project > enable "Google Calendar API".
|   2. IAM & Admin > Service Accounts > create one > Keys > Add key > JSON. Put the whole file in GOOGLE_SERVICE_ACCOUNT_JSON.
|   3. In Google Calendar open the hotel's calendar > Settings and sharing > Share with specific people > add the service account's
|      email with the permission "Make changes to events". Copy the Calendar ID into the hotel (Hotels page).
| The signed request is the standard OAuth 2.0 "JWT bearer" flow (RS256, scope .../auth/calendar). The token lasts 1 hour and is cached.
| Settings: app/config/integrations.php (read with config_item).
*/
class Google_calendar
{
    public $error = '';
    private $_lava;
    private $_account = null;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->library('http_client');
    }

    /* ------------------------------------------------ the service account ------------------------------------------------ */
    /* [client_email, private_key] from GOOGLE_SERVICE_ACCOUNT_JSON (raw JSON or base64 of it), or null */
    private function account(): ?array
    {
        if ($this->_account !== null) return $this->_account ?: null;
        $raw = trim((string) config_item('google_calendar_json'));
        if ($raw !== '' && $raw[0] !== '{') $raw = (string) base64_decode($raw, true);
        $j = json_decode($raw, true);
        $key = is_array($j) ? str_replace('\n', "\n", (string) ($j['private_key'] ?? '')) : '';
        $this->_account = (is_array($j) && !empty($j['client_email']) && str_contains($key, 'PRIVATE KEY')) ? ['email' => (string) $j['client_email'], 'key' => $key] : [];
        return $this->_account ?: null;
    }
    public function configured(): bool { return $this->account() !== null; }
    public function email(): string { return $this->account()['email'] ?? ''; }
    public function default_calendar(): string { return trim((string) config_item('google_calendar_id')); }
    /* The calendar a hotel writes to: its own, or the default one */
    public function calendar_for(array $hotel): string
    {
        $own = trim((string) ($hotel['calendar_id'] ?? ''));
        return $own !== '' ? $own : $this->default_calendar();
    }
    /* The same booking always gets the same event id, so a repeated sync updates the event instead of duplicating it (Google allows a-v and 0-9) */
    public function event_id(string $bookingRef): string { return 'hg' . md5('hotelgo-' . $bookingRef); }

    /* ------------------------------------------------ access token ------------------------------------------------ */
    private function b64(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
    private function cache_file(): string { return ROOT_DIR . 'runtime/gcal_token.json'; }

    private function token(): string
    {
        $file = $this->cache_file();
        $c = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
        if (is_array($c) && ($c['for'] ?? '') === $this->email() && ($c['exp'] ?? 0) > time() + 60) return (string) $c['token'];

        $a = $this->account();
        if (!$a) { $this->error = 'Google Calendar is not set up: GOOGLE_SERVICE_ACCOUNT_JSON is missing or not a service-account key.'; return ''; }
        $url = (string) config_item('google_token_url');
        $now = time();
        $head = $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $body = $this->b64(json_encode(['iss' => $a['email'], 'scope' => 'https://www.googleapis.com/auth/calendar', 'aud' => $url, 'iat' => $now, 'exp' => $now + 3600]));
        if (!openssl_sign($head . '.' . $body, $sig, $a['key'], OPENSSL_ALGO_SHA256)) { $this->error = 'The private key of the service account could not be read.'; return ''; }
        [$http, $res, $err] = $this->_lava->http_client->request('POST', $url, ['Content-Type: application/x-www-form-urlencoded'],
            http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $head . '.' . $body . '.' . $this->b64($sig)]), 10);
        $j = json_decode($res, true) ?: [];
        if ($err !== '' || empty($j['access_token'])) {
            $this->error = $err !== '' ? mb_substr($err, 0, 200) : 'Google refused the sign-in (HTTP ' . $http . '): ' . mb_substr((string) ($j['error_description'] ?? $j['error'] ?? 'no details'), 0, 160);
            return '';
        }
        @file_put_contents($file, json_encode(['for' => $a['email'], 'token' => $j['access_token'], 'exp' => $now + (int) ($j['expires_in'] ?? 3600)]), LOCK_EX);
        return (string) $j['access_token'];
    }

    /* ------------------------------------------------ calls ------------------------------------------------ */
    /* returns [http code, decoded json, curl error] */
    private function call(string $method, string $path, ?array $body = null): array
    {
        $t = $this->token();
        if ($t === '') return [0, [], $this->error];
        [$http, $res, $err] = $this->_lava->http_client->request($method, rtrim((string) config_item('google_calendar_api'), '/') . $path,
            ['Authorization: Bearer ' . $t, 'Content-Type: application/json', 'Accept: application/json'], $body !== null ? json_encode($body) : null, 10);
        return [$http, json_decode($res, true) ?: [], $err];
    }
    private function why(int $http, array $j, string $err): string
    {
        if ($err !== '') return mb_substr($err, 0, 200);
        $d = (string) ($j['error']['message'] ?? 'no details');
        if (in_array($http, [403, 404], true)) {
            return 'Google cannot open this calendar (HTTP ' . $http . '). Check the Calendar ID, and share the calendar with ' . $this->email() . ' with the permission "Make changes to events". Google said: ' . mb_substr($d, 0, 120);
        }
        return mb_substr('Google said (HTTP ' . $http . '): ' . $d, 0, 240);
    }
    private function path(string $calendarId, string $tail = ''): string { return '/calendars/' . rawurlencode($calendarId) . $tail; }

    /* Can we reach this calendar? Returns its name, or '' (see $this->error) */
    public function check(string $calendarId): string
    {
        $this->error = '';
        if ($calendarId === '') { $this->error = 'This hotel has no Google Calendar ID yet.'; return ''; }
        [$http, $j, $err] = $this->call('GET', $this->path($calendarId));
        if ($http !== 200) { if ($this->error === '') $this->error = $this->why($http, $j, $err); return ''; }
        return (string) ($j['summary'] ?? $calendarId);
    }

    /* Create the event, or update it when it is already there. Returns true or false (see $this->error) */
    public function save_event(string $calendarId, string $eventId, array $event): bool
    {
        $this->error = '';
        $event['status'] = 'confirmed';
        [$http, $j, $err] = $this->call('PUT', $this->path($calendarId, '/events/' . rawurlencode($eventId)), $event);       // update
        if ($http === 404) {
            [$http, $j, $err] = $this->call('POST', $this->path($calendarId, '/events'), $event + ['id' => $eventId]);          // not there yet: create
            if ($http === 409) [$http, $j, $err] = $this->call('PUT', $this->path($calendarId, '/events/' . rawurlencode($eventId)), $event);   // was deleted before: bring it back
        }
        if ($http !== 200) { if ($this->error === '') $this->error = $this->why($http, $j, $err); return false; }
        return true;
    }

    /* Remove the event. An event that is already gone counts as removed. */
    public function delete_event(string $calendarId, string $eventId): bool
    {
        $this->error = '';
        [$http, $j, $err] = $this->call('DELETE', $this->path($calendarId, '/events/' . rawurlencode($eventId)));
        if (in_array($http, [200, 204, 404, 410], true)) return true;
        if ($this->error === '') $this->error = $this->why($http, $j, $err);
        return false;
    }
}
