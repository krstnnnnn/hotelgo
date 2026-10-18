<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Payment gateway.
|  PAYMENT_PROVIDER=mock      -> a "sandbox" payment page (for school demo only)
|  PAYMENT_PROVIDER=paymongo  -> real PayMongo Checkout (GCash / Maya / card). Use TEST keys first.
*/
class PaymentModel extends Model
{
    protected $table = 'payments';

    public function provider(): string { return env_get('PAYMENT_PROVIDER', 'mock'); }

    /* The fake payment page must NEVER be open on a real (production) site unless you allow it on purpose. */
    public function mock_allowed(): bool { return !is_production() || env_get('ALLOW_MOCK_PAYMENT') === '1'; }

    /* Returns the web address where the guest must pay, or null if payments are not available. */
    public function start(array $b): ?string
    {
        if ($this->provider() === 'paymongo') {
            $key = env_get('PAYMONGO_SECRET_KEY');
            if ($key === '') return null;
            $base = rtrim(env_get('APP_URL', site_url('')), '/');
            [$code, $res] = http_json('POST', 'https://api.paymongo.com/v1/checkout_sessions', ['Authorization: Basic ' . base64_encode($key . ':')], ['data' => ['attributes' => [
                'line_items' => [['currency' => 'PHP', 'amount' => (int) round($b['total'] * 100), 'name' => 'HOTELGO booking ' . $b['booking_ref'], 'quantity' => 1]],
                'payment_method_types' => ['gcash', 'paymaya', 'card'], 'reference_number' => $b['booking_ref'], 'description' => 'HOTELGO ' . $b['booking_ref'],
                'success_url' => $base . '/pay/' . $b['booking_ref'] . '/return', 'cancel_url' => $base . '/my-bookings']]]);
            if ($code !== 200 || empty($res['data']['id'])) return null;
            q('UPDATE bookings SET pay_ref = ? WHERE id = ?', [$res['data']['id'], $b['id']]);
            return $res['data']['attributes']['checkout_url'] ?? null;
        }
        return $this->mock_allowed() ? site_url('pay/' . $b['booking_ref']) : null;
    }

    /* PayMongo: ask PayMongo (not the browser!) whether this booking was really paid. */
    public function paymongo_paid(array $b): bool
    {
        if (empty($b['pay_ref'])) return false;
        [$code, $res] = http_json('GET', 'https://api.paymongo.com/v1/checkout_sessions/' . rawurlencode($b['pay_ref']), ['Authorization: Basic ' . base64_encode(env_get('PAYMONGO_SECRET_KEY') . ':')]);
        if ($code !== 200) return false;
        foreach (($res['data']['attributes']['payments'] ?? []) as $p) {
            if (($p['attributes']['status'] ?? '') === 'paid' && (int) ($p['attributes']['amount'] ?? 0) === (int) round($b['total'] * 100)) return true;
        }
        return false;
    }

    public function refund(int $paymentId): bool
    {
        // NOTE: with a real gateway, call its refund API here first. In mock mode we only update our records.
        $p = q_one("SELECT * FROM payments WHERE id = ? AND status = 'paid'", [$paymentId]);
        if (!$p) return false;
        q("UPDATE payments SET status = 'refunded', refunded_at = ? WHERE id = ?", [now_str(), $paymentId]);
        q("UPDATE bookings SET status = 'cancelled', cancelled_at = ? WHERE id = ? AND status IN ('confirmed','pending_payment')", [now_str(), $p['booking_id']]);
        return true;
    }
}
