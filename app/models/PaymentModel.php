<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Money. Table: payments.
| This model keeps the payment records and decides what a payment means for a booking.
| Talking to PayMongo is done by the library app/libraries/Paymongo.php (it never touches the database).
|
|   payment_provider=mock      -> a "sandbox" payment page (school demo only)
|   payment_provider=paymongo  -> real PayMongo: e-wallet (payment intent) or payment link. Start with TEST keys (sk_test_...).
|                                Paid or not is found by asking PayMongo (settle()). There is no webhook.
|
| Payment records are kept for the books: they can be refunded, but never deleted from the website.
*/
class PaymentModel extends Model
{
    protected $table = 'payments';

    public $error = '';

    const COLUMNS = 'p.id, p.booking_id, p.txn_ref, p.method, p.provider, p.provider_ref, p.amount, p.status, p.created_at, p.paid_at, p.refunded_at';

    private function gateway()
    {
        return $this->call->library('paymongo');
    }
    private function bookings()
    {
        return $this->call->model('BookingModel');
    }

    /* ======================================================= create ======================================================= */
    public function add_paid(array $b, string $method, string $provider, string $providerRef): int
    {
        return (int) $this->insert([
            'booking_id' => $b['id'], 'txn_ref' => 'TXN-' . strtoupper(bin2hex(random_bytes(5))), 'method' => $method,
            'provider' => $provider, 'provider_ref' => $providerRef, 'amount' => $b['total'], 'status' => 'paid',
            'created_at' => now_str(), 'paid_at' => now_str(),
        ]);
    }
    /* Cash paid at the front desk (walk-in guests) */
    public function add_cash(array $b): int
    {
        return $this->add_paid($b, 'cash', 'frontdesk', '');
    }

    /* ======================================================= read ======================================================= */
    public function provider_ref_exists(string $provider, string $providerRef): bool
    {
        return $this->db->table($this->table)->where('provider', $provider)->where('provider_ref', $providerRef)->count() > 0;
    }
    public function count_for(int $bookingId): int
    {
        return (int) $this->db->table($this->table)->where('booking_id', $bookingId)->count();
    }
    public function for_booking(int $bookingId): array
    {
        return $this->db->table($this->table)->where('booking_id', $bookingId)->order_by('id', 'DESC')->get_all();
    }
    /* The payment shown on the receipt */
    public function receipt_for(int $bookingId): ?array
    {
        return $this->db->table($this->table)->where('booking_id', $bookingId)->in('status', ['paid', 'refunded'])->order_by('id', 'DESC')->limit(1)->get() ?: null;
    }
    public function recent(int $limit = 200): array
    {
        $this->db->table('payments p')
            ->select(self::COLUMNS . ', b.booking_ref, b.guest_name, h.name AS hotel_name')
            ->join('bookings b', 'b.id = p.booking_id')
            ->left_join('hotels h', 'h.id = b.hotel_id');
        return \Hotelgo\Support\Scope::limit($this->db, 'b.hotel_id')->order_by('p.id', 'DESC')->limit($limit)->get_all();
    }
    /* Total of one status (optionally only from a time on: paid_at for paid payments) */
    public function total(string $status, string $since = ''): float
    {
        $this->db->table('payments p')->select_sum('p.amount', 'total')->join('bookings b', 'b.id = p.booking_id')->where('p.status', $status);
        \Hotelgo\Support\Scope::limit($this->db, 'b.hotel_id');
        if ($since !== '') {
            $this->db->where('p.paid_at', '>=', $since);
        }
        return (float) ($this->db->get()['total'] ?? 0);
    }
    public function waiting_count(): int
    {
        return (int) \Hotelgo\Support\Scope::limit($this->db->table('bookings'), 'hotel_id')->where('status', 'pending_payment')->count();
    }
    /* Paid payments with their stay, for the reports page (dates are Y-m-d, both included) */
    public function paid_between(string $from, string $to): array
    {
        $this->db->table('payments p')
            ->select('p.amount, p.paid_at, b.hotel_id, b.stay_type, b.hours, b.start_at, t.name AS type_name, h.name AS hotel_name')
            ->join('bookings b', 'b.id = p.booking_id')
            ->join('room_types t', 't.id = b.room_type_id')
            ->left_join('hotels h', 'h.id = b.hotel_id');
        return \Hotelgo\Support\Scope::limit($this->db, 'b.hotel_id')
            ->where('p.status', 'paid')
            ->where('p.paid_at', '>=', $from . ' 00:00:00')
            ->where('p.paid_at', '<', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00')
            ->get_all();
    }

    /* Numbers for the reports page (revenue by day, by stay type, by room type, busy hours) */
    public function report(string $from, string $to): array
    {
        $rows  = $this->paid_between($from, $to);
        $byDay = []; $byStay = ['hourly' => 0, 'overnight' => 0]; $byType = []; $byHotel = []; $hours = 0; $hourly = 0; $heat = [];
        for ($d = strtotime($from); $d <= strtotime($to); $d += 86400) {
            $byDay[date('Y-m-d', $d)] = ['hourly' => 0, 'overnight' => 0];
        }
        foreach ($rows as $r) {
            $day = date('Y-m-d', strtotime($r['paid_at']));
            if (isset($byDay[$day])) $byDay[$day][$r['stay_type']] += (float) $r['amount'];
            $byStay[$r['stay_type']] += (float) $r['amount'];
            $label = (\Hotelgo\Support\Scope::hotel() === null && ($r['hotel_name'] ?? '') !== '') ? $r['hotel_name'] . ' · ' . $r['type_name'] : $r['type_name'];
            $byType[$label] = ($byType[$label] ?? 0) + (float) $r['amount'];
            $byHotel[$r['hotel_name'] ?? '—'] = ($byHotel[$r['hotel_name'] ?? '—'] ?? 0) + (float) $r['amount'];
            if ($r['stay_type'] === 'hourly') { $hours += (int) $r['hours']; $hourly++; }
            $k = date('w', strtotime($r['start_at'])) . '-' . date('G', strtotime($r['start_at']));
            $heat[$k] = ($heat[$k] ?? 0) + 1;
        }
        return ['from' => $from, 'to' => $to, 'total' => array_sum(array_column($rows, 'amount')), 'count' => count($rows),
            'byDay' => $byDay, 'byStay' => $byStay, 'byType' => $byType, 'byHotel' => $byHotel, 'avgHours' => $hourly ? round($hours / $hourly, 1) : 0, 'heat' => $heat];
    }

    /* ======================================================= pay ======================================================= */
    /* Mock (sandbox) only: where must the guest pay? Returns a web address or null. */
    public function start(array $b): ?string
    {
        $this->error = '';
        return $this->gateway()->mock_allowed() ? site_url('pay/' . $b['booking_ref']) : null;
    }

    /* E-wallet: returns the wallet's redirect address, or null (see $this->error) */
    public function start_wallet(array $b, string $wallet): ?string
    {
        $gw = $this->gateway();
        $r  = $gw->wallet_start($wallet, $b, abs_url('pay/' . $b['booking_ref'] . '/return'));
        return $this->remember($b, $r, $gw);
    }
    /* Payment link (card / online banking / QR): returns the checkout address, or null */
    public function start_link(array $b): ?string
    {
        $gw = $this->gateway();
        if (str_starts_with((string) ($b['pay_ref'] ?? ''), 'link_')) {          // the same booking opened again: reuse the link that is still open
            $open = $gw->link_open($b['pay_ref']);
            if ($open) {
                $this->error = '';
                return $open;
            }
        }
        return $this->remember($b, $gw->link_create($b), $gw);
    }
    private function remember(array $b, ?array $r, $gw): ?string
    {
        $this->error = $gw->error;
        if (!$r) {
            return null;
        }
        [$url, $id] = $r;
        if (($b['pay_ref'] ?? '') !== $id) {
            if (!empty($b['pay_ref'])) {
                $gw->stop($b['pay_ref']);                                        // an older unpaid link is closed so only one attempt can be paid
            }
            $this->bookings()->set_pay_ref((int) $b['id'], $id);
        }
        return $url;
    }

    /* Ask PayMongo whether this booking was paid; if yes confirm it.
       Returns ['state' => paid|pending|failed|unreachable|none, 'result' => mark_paid result or null] */
    public function settle(array $b): array
    {
        if (empty($b['pay_ref']) || $this->gateway()->provider() !== 'paymongo') {
            return ['state' => 'none', 'result' => null];
        }
        $st = $this->gateway()->state($b['pay_ref'], $b);
        if ($st['state'] !== 'paid') {
            return ['state' => $st['state'], 'result' => null];
        }
        $r = $this->bookings()->mark_paid($b['booking_ref'], $st['method'] ?: (string) $b['pay_method'] ?: 'ewallet', 'paymongo', $st['payment_id'], true);
        return ['state' => 'paid', 'result' => $r];
    }

    /* ======================================================= refund ======================================================= */
    /* Returns [ok, message] */
    public function refund(int $paymentId): array
    {
        $p = $this->db->table($this->table)->where('id', $paymentId)->in('status', ['paid', 'refund_pending'])->get();
        $bk = $p ? $this->db->table('bookings')->select('hotel_id')->where('id', $p['booking_id'])->get() : null;
        if (!$p || !$bk || !\Hotelgo\Support\Scope::allows($bk['hotel_id'])) {
            return [false, 'That payment cannot be refunded.'];
        }
        if ($p['provider'] === 'paymongo') {
            $gw = $this->gateway();
            if ($gw->key_mode() === 'invalid') {
                return [false, 'PAYMONGO_SECRET_KEY is missing, so the refund could not be sent.'];
            }
            [$accepted, $message] = $gw->refund_request($p);
            if (!$accepted) {
                $this->update($paymentId, ['status' => 'refund_pending']);
                return [false, $message];
            }
        }
        $this->update($paymentId, ['status' => 'refunded', 'refunded_at' => now_str()]);
        $this->db->table('bookings')->where('id', $p['booking_id'])->in('status', ['confirmed', 'pending_payment'])->update(['status' => 'cancelled', 'cancelled_at' => now_str()]);
        $this->call->model('CalendarModel')->sync((int) $p['booking_id']);             // a refunded booking leaves the hotel's calendar
        return [true, 'Refunded.'];
    }

    /* Refund every paid payment of a cancelled booking. Returns false if a refund still needs staff attention. */
    public function refund_booking(array $b): bool
    {
        $all = true;
        foreach ($this->db->table($this->table)->select('id')->where('booking_id', $b['id'])->in('status', ['paid', 'refund_pending'])->get_all() as $p) {
            [$ok] = $this->refund((int) $p['id']);
            $all = $all && $ok;
        }
        if (!empty($b['pay_ref']) && $b['status'] === 'pending_payment') {
            $this->gateway()->stop($b['pay_ref']);
        }
        return $all;
    }
}
