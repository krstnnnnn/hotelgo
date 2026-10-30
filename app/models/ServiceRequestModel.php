<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Service requests. Table: service_requests.
| A guest in a VIP room (or the front desk for any guest) asks for something extra: private dining, karaoke, a balcony set-up ...
| The motel team sees it in the console (Requests page, bell and badge), accepts it, may set a PRICE, and marks it done. Or declines it.
| An extra price is paid in cash at the desk or with a PayMongo payment link; the money is saved as one more payment of the booking.
|
|   new  >  accepted (price optional)  >  done          or          declined
*/
class ServiceRequestModel extends Model
{
    protected $table = 'service_requests';

    const STATUSES = ['new', 'accepted', 'done', 'declined'];
    const COLUMNS  = 'r.id, r.booking_id, r.hotel_id, r.service, r.note, r.status, r.price, r.pay_ref, r.paid_at, r.source, r.created_at, r.updated_at, '
        . 'b.booking_ref, b.guest_name, b.guest_mobile, b.status AS booking_status, rm.room_no, t.name AS type_name, h.name AS hotel_name';

    public $error = '';

    private function gateway()       { return $this->call->library('paymongo'); }
    private function payments()      { return $this->call->model('PaymentModel'); }
    private function notifications() { return $this->call->model('NotificationModel'); }

    /* what a guest can pick from (app/config/hotelgo.php) */
    public function services(): array
    {
        return array_values((array) config_item('hotelgo_request_services'));
    }

    /* ======================================================= read ======================================================= */
    private function base()
    {
        $this->db->table('service_requests r')->select(self::COLUMNS)
            ->join('bookings b', 'b.id = r.booking_id')
            ->join('room_types t', 't.id = b.room_type_id')
            ->left_join('rooms rm', 'rm.id = b.room_id')
            ->left_join('hotels h', 'h.id = r.hotel_id');
        return \Hotelgo\Support\Scope::limit($this->db, 'r.hotel_id');        // hotel staff only see the requests of their own hotel
    }
    public function by_id(int $id): ?array
    {
        return $this->base()->where('r.id', $id)->get() ?: null;
    }
    /* $status: open (new + accepted) | all | new | accepted | done | declined */
    public function listing(string $status = 'open', int $limit = 200): array
    {
        $this->base();
        if ($status === 'open') $this->db->in('r.status', ['new', 'accepted']);
        elseif (in_array($status, self::STATUSES, true)) $this->db->where('r.status', $status);
        return $this->db->order_by('r.id', 'DESC')->limit($limit)->get_all();
    }
    public function for_booking(int $bookingId): array
    {
        return $this->db->table($this->table)->where('booking_id', $bookingId)->order_by('id', 'DESC')->get_all();
    }
    public function counts(): array
    {
        $out = array_fill_keys(self::STATUSES, 0);
        $this->db->table($this->table)->select('status, COUNT(*) AS n');
        foreach (\Hotelgo\Support\Scope::limit($this->db, 'hotel_id')->group_by('status')->get_all() as $r) {
            $out[$r['status']] = (int) $r['n'];
        }
        return $out;
    }
    public function count_new(): int
    {
        return (int) \Hotelgo\Support\Scope::limit($this->db->table($this->table), 'hotel_id')->where('status', 'new')->count();
    }
    /* money still to collect for this request? */
    public function owing(array $r): bool
    {
        return (float) ($r['price'] ?? 0) > 0 && empty($r['paid_at']) && in_array($r['status'], ['accepted', 'done'], true);
    }

    /* ======================================================= create ======================================================= */
    /* $b is a booking (BookingModel). Returns [ok, message or request id]. A guest may only ask from the services list; the front desk may type any service. */
    public function create(array $b, string $service, string $note, string $source = 'guest', ?float $price = null): array
    {
        $service = trim($service); $note = trim($note);
        if (!in_array($b['status'], ['confirmed', 'checked_in'], true)) return [false, 'Requests can be made for a confirmed booking only.'];
        if ($source === 'guest') {
            if (!(int) ($b['is_vip'] ?? 0)) return [false, 'Extra service requests are for VIP rooms. Please ask the front desk.'];
            if (!in_array($service, $this->services(), true)) return [false, 'Please choose one of the listed services.'];
        } elseif (!len_ok($service, 2, 120)) {
            return [false, 'Enter the service (2 to 120 letters).'];
        }
        if (mb_strlen($note) > 500) return [false, 'The note is too long (500 characters at most).'];
        if ($price !== null && ($price < 0 || $price > 100000)) return [false, 'The price must be between 0 and 100,000.'];
        $open = $this->db->table($this->table)->where('booking_id', $b['id'])->in('status', ['new', 'accepted'])->count();
        if ($open >= (int) config_item('hotelgo_max_open_requests')) return [false, 'There are already many open requests for this booking. Please wait for the team to answer.'];

        $staff = $source === 'staff';
        $id = (int) $this->insert([
            'booking_id' => $b['id'], 'hotel_id' => $b['hotel_id'] ?? null, 'service' => $service, 'note' => $note !== '' ? $note : null,
            'status' => $staff ? 'accepted' : 'new', 'price' => $staff ? $price : null, 'source' => $staff ? 'staff' : 'guest', 'created_at' => now_str(),
        ]);
        if (!$staff) $this->notifications()->request_received($b, $this->by_id_any($id));
        return [true, $id];
    }
    /* a request without the hotel filter (the guest is not a hotel user) */
    private function by_id_any(int $id): array
    {
        return $this->db->table($this->table)->where('id', $id)->get() ?: [];
    }

    /* ======================================================= decide ======================================================= */
    /* $do: accept | decline | done. Returns [ok, message]. */
    public function decide(array $r, string $do, ?float $price, ?int $userId, string $reply = ''): array
    {
        $paid = !empty($r['paid_at']);
        if ($price !== null && ($price < 0 || $price > 100000)) return [false, 'The price must be between 0 and 100,000.'];
        $set = ['updated_at' => now_str(), 'handled_by' => $userId];
        switch ($do) {
            case 'accept':
                if (!in_array($r['status'], ['new', 'accepted'], true)) return [false, 'This request is already ' . $r['status'] . '.'];
                if ($paid && $price !== null && (float) $price !== (float) $r['price']) return [false, 'This request was already paid, so the price cannot change.'];
                $set['status'] = 'accepted';
                if (!$paid && $price !== null) {                 // no price sent = keep the price already set (0 = make it free)
                    $set['price'] = $price > 0 ? $price : null;
                    if ((float) ($set['price'] ?? 0) !== (float) ($r['price'] ?? 0) && !empty($r['pay_ref'])) { $this->gateway()->stop($r['pay_ref']); $set['pay_ref'] = null; }   // the old link had another amount
                }
                $msg = 'Request accepted.';
                break;
            case 'done':
                if (!in_array($r['status'], ['new', 'accepted'], true)) return [false, 'This request is already ' . $r['status'] . '.'];
                if ($price !== null && !$paid) $set['price'] = $price > 0 ? $price : null;
                $set['status'] = 'done';
                $msg = 'Marked as done.';
                break;
            case 'decline':
                if (!in_array($r['status'], ['new', 'accepted'], true)) return [false, 'This request is already ' . $r['status'] . '.'];
                if ($paid) return [false, 'This request was already paid. Refund the payment first.'];
                if (!empty($r['pay_ref'])) { $this->gateway()->stop($r['pay_ref']); $set['pay_ref'] = null; }
                $set['status'] = 'declined';
                $msg = 'Request declined.';
                break;
            default:
                return [false, 'Unknown action.'];
        }
        $this->update((int) $r['id'], $set);
        $now = $this->by_id((int) $r['id']);
        if ($now) $this->notifications()->request_update($now, $reply);
        return [true, $msg];
    }

    /* ======================================================= pay ======================================================= */
    /* The guest paid in cash at the desk */
    public function collect_cash(array $r): array
    {
        if (!$this->owing($r)) return [false, 'Nothing to collect for this request.'];
        $this->payments()->add_extra(['id' => $r['booking_id']], (float) $r['price'], 'cash', 'frontdesk', '');
        $this->update((int) $r['id'], ['paid_at' => now_str(), 'updated_at' => now_str()]);
        if (!empty($r['pay_ref'])) $this->gateway()->stop($r['pay_ref']);                       // an open link must not be paid twice
        return [true, 'Cash received. The payment is saved.'];
    }
    /* A PayMongo payment link for the price. Returns [checkout address or null, error] */
    public function start_link(array $r): array
    {
        $gw = $this->gateway();
        if ($gw->provider() !== 'paymongo') return [null, 'Online payment is not switched on. Collect it in cash instead.'];
        if (!$this->owing($r)) return [null, 'Nothing to pay for this request.'];
        if (str_starts_with((string) ($r['pay_ref'] ?? ''), 'link_') && ($open = $gw->link_open($r['pay_ref']))) return [$open, ''];     // reuse the link that is still open
        $made = $gw->link_create(['total' => $r['price'], 'booking_ref' => $r['booking_ref'], 'type_name' => $r['service']]);
        if (!$made) return [null, $gw->error];
        if (!empty($r['pay_ref'])) $gw->stop($r['pay_ref']);
        $this->update((int) $r['id'], ['pay_ref' => $made[1], 'updated_at' => now_str()]);
        return [$made[0], ''];
    }
    /* Ask PayMongo whether the link was paid; if yes save the payment. Returns paid | pending | failed | unreachable | none */
    public function settle(array $r): string
    {
        $gw = $this->gateway();
        if (empty($r['pay_ref']) || !empty($r['paid_at']) || $gw->provider() !== 'paymongo') return 'none';
        $st = $gw->state($r['pay_ref'], ['total' => $r['price']]);
        if ($st['state'] !== 'paid') return $st['state'];
        $pay = $this->payments();
        if (!$pay->provider_ref_exists('paymongo', $st['payment_id'])) {
            $pay->add_extra(['id' => $r['booking_id']], (float) $r['price'], $st['method'] ?: 'ewallet', 'paymongo', $st['payment_id']);
        }
        $this->update((int) $r['id'], ['paid_at' => now_str(), 'updated_at' => now_str()]);
        return 'paid';
    }
    /* settle the open links of a list of requests (a few at a time, so a page never waits long) */
    public function settle_many(array $rows, int $max = 5): void
    {
        foreach ($rows as $r) {
            if ($max <= 0) return;
            if (!empty($r['pay_ref']) && empty($r['paid_at'])) { $this->settle($r); $max--; }
        }
    }
}
