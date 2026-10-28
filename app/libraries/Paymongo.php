<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| PayMongo gateway (GCash / Maya / card ...). Talks to PayMongo only: it never touches our database.
| Start with TEST keys (sk_test_...). Settings: app/config/integrations.php (config_item).
| Load it with:  $this->call->library('paymongo');   then:  $this->paymongo->create_session($booking)
|
| How a real payment travels:
|  1. Guest clicks "Pay"      -> create_session() makes a PayMongo Checkout Session; we send the guest to its page.
|  2. Guest pays on PayMongo  -> PayMongo calls our WEBHOOK (/webhooks/paymongo) AND sends the guest back to /pay/{ref}/return.
|  3. We never trust the browser or the webhook text alone: we ask PayMongo's API "was this session really paid, and for the right amount?"
*/
class Paymongo
{
    public $error = '';
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->library('http_client');
    }

    public function provider(): string { return (string) config_item('payment_provider'); }
    /* The fake payment page must NEVER be open on a real (production) site unless you allow it on purpose. */
    public function mock_allowed(): bool { return !is_production() || (bool) config_item('hotelgo_allow_mock_payment'); }
    public function key(): string { return (string) config_item('paymongo_secret_key'); }
    public function key_mode(): string { $k = $this->key(); return str_starts_with($k, 'sk_test_') ? 'test' : (str_starts_with($k, 'sk_live_') ? 'live' : 'invalid'); }
    public function webhook_secret(): string { return (string) config_item('paymongo_webhook_secret'); }

    private function headers(): array { return ['Authorization: Basic ' . base64_encode($this->key() . ':'), 'Content-Type: application/json', 'Accept: application/json']; }
    /* returns [http code, decoded json, curl error] */
    protected function call(string $method, string $path, ?array $body = null): array
    {
        [$http, $res, $err] = $this->_lava->http_client->request($method, config_item('paymongo_api') . $path, $this->headers(), $body !== null ? json_encode($body) : null);
        return [$http, json_decode($res, true) ?: [], $err];
    }
    public function api_error(int $http, array $j, string $err): string
    {
        if ($err !== '') return mb_substr($err, 0, 200);
        $d = $j['errors'][0]['detail'] ?? ($j['errors'][0]['code'] ?? '');
        return mb_substr('PayMongo said (HTTP ' . $http . '): ' . ($d ?: 'no details'), 0, 240);
    }

    /* which PayMongo payment methods to offer for the guest's choice (e-wallet / card / bank) */
    public function methods_for(string $choice): array
    {
        $allowed = array_values(array_filter(array_map('trim', explode(',', (string) config_item('paymongo_methods')))));
        $groups = ['ewallet' => ['gcash', 'paymaya', 'grab_pay', 'qrph'], 'card' => ['card'], 'bank' => ['dob', 'dob_ubp', 'brankas_bdo', 'brankas_landbank', 'brankas_metrobank']];
        return array_values(array_intersect($allowed, $groups[$choice] ?? [])) ?: $allowed;
    }
    public function method_from(array $session, string $fallback): string
    {
        $t = $session['attributes']['payments'][0]['attributes']['source']['type'] ?? '';
        if ($t === 'card') return 'card';
        if (in_array($t, ['gcash', 'paymaya', 'grab_pay', 'qrph'], true)) return 'ewallet';
        if ($t !== '') return 'bank';
        return $fallback ?: 'ewallet';
    }

    /* ----- 1. build the "create checkout session" request (pure function, easy to test) ----- */
    public function session_request(array $b): array
    {
        $base = rtrim(abs_url(''), '/');
        return ['data' => ['attributes' => [
            'line_items' => [['name' => 'HOTELGO ' . $b['type_name'] . ' · ' . $b['booking_ref'], 'amount' => (int) round($b['total'] * 100), 'currency' => 'PHP', 'quantity' => 1,
                'description' => stay_label($b) . ' · ' . fmt_dt($b['start_at'], 'j M Y g:i A')]],
            'payment_method_types' => $this->methods_for($b['pay_method'] ?? ''),
            'reference_number' => $b['booking_ref'], 'description' => 'HOTELGO booking ' . $b['booking_ref'],
            'success_url' => $base . '/pay/' . $b['booking_ref'] . '/return', 'cancel_url' => $base . '/booking/' . $b['booking_ref'],
            'send_email_receipt' => false, 'show_description' => true, 'show_line_items' => true,
        ]]];
    }

    /* Creates the session. Returns [checkout url, session id] or null (see $this->error). */
    public function create_session(array $b): ?array
    {
        $this->error = '';
        if ($this->key_mode() === 'invalid') { $this->error = 'PAYMONGO_SECRET_KEY is missing or does not start with sk_test_ / sk_live_.'; return null; }
        if (!empty($b['pay_ref'])) {                                       // same booking opened again: reuse the session that is still open (never two payable sessions)
            $s = $this->session_get($b['pay_ref']);
            if ($s && ($s['attributes']['status'] ?? '') === 'active' && !empty($s['attributes']['checkout_url'])) return [$s['attributes']['checkout_url'], $b['pay_ref']];
        }
        [$http, $j, $err] = $this->call('POST', '/checkout_sessions', $this->session_request($b));
        if ($err !== '' || $http !== 200 || empty($j['data']['id']) || empty($j['data']['attributes']['checkout_url'])) { $this->error = $this->api_error($http, $j, $err); return null; }
        return [$j['data']['attributes']['checkout_url'], $j['data']['id']];
    }

    /* ----- 2. ask PayMongo about a session (null = could not reach PayMongo) ----- */
    public function session_get(string $id): ?array
    {
        if (!preg_match('/^cs_[A-Za-z0-9]+$/', $id)) return null;
        [$http, $j, $err] = $this->call('GET', '/checkout_sessions/' . $id);
        return ($err === '' && $http === 200 && !empty($j['data'])) ? $j['data'] : null;
    }
    public function session_expire(string $id): void
    {
        if ($this->provider() === 'paymongo' && preg_match('/^cs_[A-Za-z0-9]+$/', $id)) $this->call('POST', '/checkout_sessions/' . $id . '/expire');
    }
    /* Was the session paid, for exactly the booking total? Returns the PayMongo payment id (pay_...) or null. */
    public function session_paid(array $session, array $b): ?string
    {
        foreach (($session['attributes']['payments'] ?? []) as $p) {
            $a = $p['attributes'] ?? [];
            if (($a['status'] ?? '') === 'paid' && (int) ($a['amount'] ?? 0) === (int) round($b['total'] * 100)) return (string) ($p['id'] ?? 'paid');
        }
        return null;
    }

    /* ----- 3. webhook: check the signature, read the event ----- */
    /* header looks like  t=1496734173,te=<test signature>,li=<live signature> ; signature = HMAC-SHA256(secret, t + "." + raw body) */
    public function verify_signature(string $raw, string $header, string $secret): ?string
    {
        $parts = [];
        foreach (explode(',', $header) as $kv) { $kv = explode('=', trim($kv), 2); if (count($kv) === 2) $parts[$kv[0]] = $kv[1]; }
        if ($secret === '' || empty($parts['t']) || !ctype_digit($parts['t'])) return null;
        $expected = hash_hmac('sha256', $parts['t'] . '.' . $raw, $secret);
        if (!empty($parts['te']) && hash_equals($expected, $parts['te'])) return 'test';
        if (!empty($parts['li']) && hash_equals($expected, $parts['li'])) return 'live';
        return null;
    }
    /* PayMongo documents two shapes of the event; we accept both. Returns [event type, checkout session object] */
    public function extract_event(array $j): array
    {
        if (isset($j['data']['attributes']['type'], $j['data']['attributes']['data'])) return [$j['data']['attributes']['type'], $j['data']['attributes']['data']];
        if (isset($j['data']['type'], $j['data']['data'])) return [$j['data']['type'], $j['data']['data']];
        return [null, null];
    }

    /* ----- refunds. Returns [accepted, message] ----- */
    public function refund_request(array $payment): array
    {
        if ($this->key_mode() === 'invalid') return [false, 'PAYMONGO_SECRET_KEY is missing, so the refund could not be sent.'];
        [$http, $j, $err] = $this->call('POST', '/refunds', ['data' => ['attributes' => ['amount' => (int) round($payment['amount'] * 100), 'payment_id' => $payment['provider_ref'], 'reason' => 'requested_by_customer', 'notes' => 'HOTELGO ' . $payment['txn_ref']]]]);
        if ($err !== '' || $http < 200 || $http >= 300) return [false, 'PayMongo did not accept the refund yet. It is marked "refund pending". ' . $this->api_error($http, $j, $err)];
        return [true, 'Refunded.'];
    }
}
