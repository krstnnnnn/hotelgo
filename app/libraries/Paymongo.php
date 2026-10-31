<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| PayMongo gateway. Talks to PayMongo only: it never touches our database.
| Keys: PAYMONGO_PUBLIC_KEY + PAYMONGO_SECRET_KEY (start with the TEST keys pk_test_ / sk_test_). There is NO webhook.
| Load it with:  $this->call->library('paymongo');   then:  $this->paymongo->wallet_start('gcash', $booking, $returnUrl)
|
| Two ways to pay (the same two methods as the reference project):
|  1. E-wallet (gcash, paymaya, grab_pay, shopeepay) -> Payment Intent: create intent, create method, attach, redirect the guest.
|  2. Payment link (card, online banking, QR)         -> Links API: create a link, send the guest to its checkout page.
| Then WE ask PayMongo "was it paid, for the right amount?" (return page, status poll, staff "Check payment"). Nobody has to call us.
| booking.pay_ref keeps the PayMongo id: pi_... (intent) or link_... (link).
*/
class Paymongo
{
    public const WALLETS = ['gcash' => 'GCash', 'paymaya' => 'Maya', 'grab_pay' => 'GrabPay', 'shopeepay' => 'ShopeePay'];

    public $error = '';
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->library('http_client');
    }

    public function provider(): string { return 'paymongo'; }
    /* true when PAYMONGO_SECRET_KEY is a real sk_test_ / sk_live_ key: online payment is switched on */
    public function configured(): bool { return $this->key_mode() !== 'invalid'; }
    public function key(): string { return (string) config_item('paymongo_secret_key'); }
    public function public_key(): string { return (string) config_item('paymongo_public_key'); }
    public function key_mode(): string { $k = $this->key(); return str_starts_with($k, 'sk_test_') ? 'test' : (str_starts_with($k, 'sk_live_') ? 'live' : 'invalid'); }

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
    private function ready(): bool
    {
        $this->error = '';
        if ($this->key_mode() === 'invalid') { $this->error = 'PAYMONGO_SECRET_KEY is missing or does not start with sk_test_ / sk_live_.'; return false; }
        return true;
    }
    private function centavos(array $b): int { return (int) round($b['total'] * 100); }

    /* Which e-wallets are offered (PAYMONGO_METHODS may list gcash, paymaya, grab_pay, shopeepay; card/bank use the link) */
    public function wallets(): array
    {
        $allowed = array_values(array_filter(array_map('trim', explode(',', (string) config_item('paymongo_methods')))));
        $w = array_values(array_intersect(array_keys(self::WALLETS), $allowed));
        return $w ?: ['gcash', 'paymaya'];
    }
    public function method_group(string $type): string
    {
        if ($type === 'card') return 'card';
        if (isset(self::WALLETS[$type]) || $type === 'qrph') return 'ewallet';
        return $type !== '' ? 'bank' : '';
    }

    /* ----- 1a. e-wallet. Returns [redirect url, intent id] or null (see $this->error) ----- */
    public function wallet_start(string $wallet, array $b, string $returnUrl): ?array
    {
        if (!$this->ready()) return null;
        if (!isset(self::WALLETS[$wallet])) { $this->error = 'Unknown e-wallet.'; return null; }
        [$h, $pi, $e] = $this->call('POST', '/payment_intents', ['data' => ['attributes' => [
            'amount' => $this->centavos($b), 'payment_method_allowed' => [$wallet], 'currency' => 'PHP', 'capture_type' => 'automatic',
            'description' => 'HOTELGO booking ' . $b['booking_ref'], 'statement_descriptor' => 'HOTELGO', 'metadata' => ['booking_ref' => $b['booking_ref']]]]]);
        $id = $pi['data']['id'] ?? ''; $client = $pi['data']['attributes']['client_key'] ?? '';
        if ($e !== '' || $h !== 200 || $id === '' || $client === '') { $this->error = $this->api_error($h, $pi, $e); return null; }
        [$h, $pm, $e] = $this->call('POST', '/payment_methods', ['data' => ['attributes' => ['type' => $wallet]]]);
        $pmId = $pm['data']['id'] ?? '';
        if ($e !== '' || $h !== 200 || $pmId === '') { $this->error = $this->api_error($h, $pm, $e); return null; }
        [$h, $at, $e] = $this->call('POST', '/payment_intents/' . $id . '/attach', ['data' => ['attributes' => ['payment_method' => $pmId, 'client_key' => $client, 'return_url' => $returnUrl]]]);
        $url = $at['data']['attributes']['next_action']['redirect']['url'] ?? '';
        if ($e !== '' || $h !== 200 || $url === '') { $this->error = $this->api_error($h, $at, $e) ?: 'PayMongo gave no redirect address.'; return null; }
        return [$url, $id];
    }

    /* ----- 1b. payment link (card, online banking, QR). Returns [checkout url, link id] or null ----- */
    public function link_create(array $b): ?array
    {
        if (!$this->ready()) return null;
        [$h, $j, $e] = $this->call('POST', '/links', ['data' => ['attributes' => [
            'amount' => $this->centavos($b), 'description' => 'HOTELGO ' . ($b['type_name'] ?? 'room') . ' · ' . $b['booking_ref'], 'remarks' => $b['booking_ref']]]]);
        $id = $j['data']['id'] ?? ''; $url = $j['data']['attributes']['checkout_url'] ?? '';
        if ($e !== '' || $h !== 200 || $id === '' || $url === '') { $this->error = $this->api_error($h, $j, $e); return null; }
        return [$url, $id];
    }
    public function link_archive(string $id): void
    {
        if ($this->key_mode() !== 'invalid' && preg_match('/^link_[A-Za-z0-9]+$/', $id)) $this->call('POST', '/links/' . $id . '/archive');
    }
    /* the open link of a booking, so opening "Pay" twice never makes two payable links */
    public function link_open(string $id): ?string
    {
        if (!preg_match('/^link_[A-Za-z0-9]+$/', $id)) return null;
        [$h, $j, $e] = $this->call('GET', '/links/' . $id);
        return ($e === '' && $h === 200 && ($j['data']['attributes']['status'] ?? '') === 'unpaid') ? ($j['data']['attributes']['checkout_url'] ?? null) : null;
    }

    /* ----- 2. ask PayMongo. Returns ['state' => paid|pending|failed|unreachable, 'payment_id', 'method'] ----- */
    public function state(string $payRef, array $b): array
    {
        $none = ['state' => 'unreachable', 'payment_id' => '', 'method' => ''];
        if ($this->key_mode() === 'invalid') return $none;
        $total = $this->centavos($b);
        if (preg_match('/^pi_[A-Za-z0-9]+$/', $payRef)) {
            [$h, $j, $e] = $this->call('GET', '/payment_intents/' . $payRef);
            if ($e !== '' || $h !== 200 || empty($j['data'])) return $none;
            $a = $j['data']['attributes'] ?? []; $st = (string) ($a['status'] ?? '');
            if ($st === 'succeeded') {
                $pay = $a['payments'][0] ?? [];
                if ((int) ($a['amount'] ?? 0) !== $total) return ['state' => 'failed', 'payment_id' => '', 'method' => ''];
                return ['state' => 'paid', 'payment_id' => (string) ($pay['id'] ?? $payRef), 'method' => $this->method_group((string) ($pay['attributes']['source']['type'] ?? $a['payment_method_allowed'][0] ?? ''))];
            }
            $lastErr = $a['last_payment_error'] ?? null;
            return ['state' => $lastErr ? 'failed' : 'pending', 'payment_id' => '', 'method' => ''];
        }
        if (preg_match('/^link_[A-Za-z0-9]+$/', $payRef)) {
            [$h, $j, $e] = $this->call('GET', '/links/' . $payRef);
            if ($e !== '' || $h !== 200 || empty($j['data'])) return $none;
            $a = $j['data']['attributes'] ?? [];
            if (($a['status'] ?? '') === 'paid' && (int) ($a['amount'] ?? 0) === $total) {
                $p = $a['payments'][0] ?? [];
                $pid = (string) ($p['data']['id'] ?? $p['id'] ?? $payRef);
                $type = (string) ($p['data']['attributes']['source']['type'] ?? $p['source']['type'] ?? '');
                return ['state' => 'paid', 'payment_id' => $pid, 'method' => $this->method_group($type) ?: 'card'];
            }
            return ['state' => (($a['status'] ?? '') === 'archived') ? 'failed' : 'pending', 'payment_id' => '', 'method' => ''];
        }
        return $none;
    }
    /* stop an unpaid attempt (booking cancelled / expired) */
    public function stop(string $payRef): void
    {
        if (str_starts_with($payRef, 'link_')) $this->link_archive($payRef);
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
