<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Payment gateway.
|  PAYMENT_PROVIDER=mock      -> a "sandbox" payment page (school demo only)
|  PAYMENT_PROVIDER=paymongo  -> real PayMongo Hosted Checkout (GCash / Maya / card ...). Start with TEST keys (sk_test_...).
|
| How a real payment travels:
|  1. Guest clicks "Pay"      -> we create a PayMongo Checkout Session and send the guest to PayMongo's page.
|  2. Guest pays on PayMongo  -> PayMongo calls our WEBHOOK (/webhooks/paymongo) and ALSO sends the guest back to /pay/{ref}/return.
|  3. We never trust the browser or the webhook text alone: we ask PayMongo's API "was this session really paid, and for the right amount?"
*/
class PaymentModel extends Model
{
    protected $table = 'payments';
    public $error = '';
    const API = 'https://api.paymongo.com/v1';

    public function provider(): string { return env_get('PAYMENT_PROVIDER', 'mock'); }
    /* The fake payment page must NEVER be open on a real (production) site unless you allow it on purpose. */
    public function mock_allowed(): bool { return !is_production() || env_get('ALLOW_MOCK_PAYMENT') === '1'; }
    public function key(): string { return env_get('PAYMONGO_SECRET_KEY'); }
    public function key_mode(): string { $k = $this->key(); return str_starts_with($k, 'sk_test_') ? 'test' : (str_starts_with($k, 'sk_live_') ? 'live' : 'invalid'); }

    private function headers(): array { return ['Authorization: Basic ' . base64_encode($this->key() . ':'), 'Content-Type: application/json', 'Accept: application/json']; }
    /* returns [http code, decoded json, curl error] */
    protected function call(string $method, string $path, ?array $body = null): array
    {
        [$http, $res, $err] = http_request($method, self::API . $path, $this->headers(), $body !== null ? json_encode($body) : null);
        return [$http, json_decode($res, true) ?: [], $err];
    }
    private function api_error(int $http, array $j, string $err): string
    {
        if ($err !== '') return mb_substr($err, 0, 200);
        $d = $j['errors'][0]['detail'] ?? ($j['errors'][0]['code'] ?? '');
        return mb_substr('PayMongo said (HTTP ' . $http . '): ' . ($d ?: 'no details'), 0, 240);
    }

    /* which PayMongo payment methods to offer for the guest's choice (e-wallet / card / bank) */
    public function methods_for(string $choice): array
    {
        $allowed = array_values(array_filter(array_map('trim', explode(',', env_get('PAYMONGO_METHODS', 'gcash,paymaya,card')))));
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
        $base = rtrim(env_get('APP_URL'), '/') ?: rtrim(abs_url(''), '/');
        return ['data' => ['attributes' => [
            'line_items' => [['name' => 'HOTELGO ' . $b['type_name'] . ' · ' . $b['booking_ref'], 'amount' => (int) round($b['total'] * 100), 'currency' => 'PHP', 'quantity' => 1,
                'description' => stay_label($b) . ' · ' . fmt_dt($b['start_at'], 'j M Y g:i A')]],
            'payment_method_types' => $this->methods_for($b['pay_method'] ?? ''),
            'reference_number' => $b['booking_ref'], 'description' => 'HOTELGO booking ' . $b['booking_ref'],
            'success_url' => $base . '/pay/' . $b['booking_ref'] . '/return', 'cancel_url' => $base . '/booking/' . $b['booking_ref'],
            'send_email_receipt' => false, 'show_description' => true, 'show_line_items' => true,
        ]]];
    }

    /* Returns the web address where the guest must pay, or null (see $this->error). */
    public function start(array $b): ?string
    {
        $this->error = '';
        if ($this->provider() === 'paymongo') {
            if ($this->key_mode() === 'invalid') { $this->error = 'PAYMONGO_SECRET_KEY is missing or does not start with sk_test_ / sk_live_.'; return null; }
            if (!empty($b['pay_ref'])) {                                       // same booking opened again: reuse the session that is still open (never two payable sessions)
                $s = $this->session_get($b['pay_ref']);
                if ($s && ($s['attributes']['status'] ?? '') === 'active' && !empty($s['attributes']['checkout_url'])) return $s['attributes']['checkout_url'];
            }
            [$http, $j, $err] = $this->call('POST', '/checkout_sessions', $this->session_request($b));
            if ($err !== '' || $http !== 200 || empty($j['data']['id']) || empty($j['data']['attributes']['checkout_url'])) { $this->error = $this->api_error($http, $j, $err); return null; }
            q('UPDATE bookings SET pay_ref = ? WHERE id = ?', [$j['data']['id'], $b['id']]);
            return $j['data']['attributes']['checkout_url'];
        }
        return $this->mock_allowed() ? site_url('pay/' . $b['booking_ref']) : null;
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

    /* A paid session arrived (webhook). Returns [result, booking] */
    public function handle_paid_session(array $session): array
    {
        $id = (string) ($session['id'] ?? ''); $ref = (string) ($session['attributes']['reference_number'] ?? '');
        if (!preg_match('/^cs_[A-Za-z0-9]+$/', $id)) return ['invalid', null];
        $b = q_one('SELECT * FROM bookings WHERE pay_ref = ?', [$id]) ?: (preg_match('/^HG-[A-Z0-9]{6}$/', $ref) ? q_one('SELECT * FROM bookings WHERE booking_ref = ?', [$ref]) : null);
        if (!$b) return ['unknown_booking', null];
        $live = $this->session_get($id);                                       // do not trust the webhook text alone: ask PayMongo
        if ($live === null) return ['retry', $b];                              // could not reach PayMongo: answer with an error so PayMongo tries again later
        $payId = $this->session_paid($live, $b);
        if (!$payId) return ['not_paid', $b];
        if ((int) q_val('SELECT COUNT(*) FROM payments WHERE provider = ? AND provider_ref = ?', ['paymongo', $payId]) > 0) return ['already', $b];
        $r = (new BookingModel())->mark_paid($b['booking_ref'], $this->method_from($live, (string) $b['pay_method']), 'paymongo', $payId, true);
        return [$r['status'], $r['booking']];
    }

    /* ----- refunds ----- */
    /* returns [ok, message] */
    public function refund(int $paymentId): array
    {
        $p = q_one("SELECT * FROM payments WHERE id = ? AND status IN ('paid','refund_pending')", [$paymentId]);
        if (!$p) return [false, 'That payment cannot be refunded.'];
        if ($p['provider'] === 'paymongo') {
            if ($this->key_mode() === 'invalid') return [false, 'PAYMONGO_SECRET_KEY is missing, so the refund could not be sent.'];
            [$http, $j, $err] = $this->call('POST', '/refunds', ['data' => ['attributes' => ['amount' => (int) round($p['amount'] * 100), 'payment_id' => $p['provider_ref'], 'reason' => 'requested_by_customer', 'notes' => 'HOTELGO ' . $p['txn_ref']]]]);
            if ($err !== '' || $http < 200 || $http >= 300) {
                q("UPDATE payments SET status = 'refund_pending' WHERE id = ?", [$paymentId]);
                return [false, 'PayMongo did not accept the refund yet. It is marked "refund pending". ' . $this->api_error($http, $j, $err)];
            }
        }
        q("UPDATE payments SET status = 'refunded', refunded_at = ? WHERE id = ?", [now_str(), $paymentId]);
        q("UPDATE bookings SET status = 'cancelled', cancelled_at = ? WHERE id = ? AND status IN ('confirmed','pending_payment')", [now_str(), $p['booking_id']]);
        return [true, 'Refunded.'];
    }
    /* refund every paid payment of a cancelled booking. Returns false if any refund still needs staff attention. */
    public function refund_booking(array $b): bool
    {
        $all = true;
        foreach (q_all("SELECT id FROM payments WHERE booking_id = ? AND status IN ('paid','refund_pending')", [$b['id']]) as $p) { [$ok] = $this->refund((int) $p['id']); $all = $all && $ok; }
        if (!empty($b['pay_ref']) && $b['status'] === 'pending_payment') $this->session_expire($b['pay_ref']);
        return $all;
    }
}
