<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| HOTELGO JSON API: service requests (private dining, karaoke, balcony set-up ...) of VIP guests, handled by the motel team.
|   GET  /api/requests?status=open|new|accepted|done|declined|all   the list + counts + the service names
|   POST /api/bookings/{ref}/requests                                the front desk adds a request / extra charge for a booking
|   PUT  /api/requests/{id}                                          { do: accept | decline | done, price?, reply? }
|   POST /api/requests/{id}/pay                                      { do: cash | link | check }   (the extra price)
| Front desk and owners can all use it. Hotel staff only ever see the requests of their own hotel (Scope).
*/
class ApiRequestController extends \Hotelgo\Support\ApiBase
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model(['BookingModel', 'ServiceRequestModel', 'NotificationModel']);
        $this->call->library('paymongo');
    }

    private function row(array $r): array
    {
        return $r + ['owing' => $this->ServiceRequestModel->owing($r)];
    }
    private function request(int $id): array
    {
        return $this->ServiceRequestModel->by_id($id) ?: $this->fail('Request not found.', 404);
    }

    public function requests()                       // GET
    {
        $this->need('GET'); $this->who();
        $q = $this->api->get_query_params();
        $status = in_array($q['status'] ?? 'open', ['open', 'all', 'new', 'accepted', 'done', 'declined'], true) ? ($q['status'] ?? 'open') : 'open';
        $rows = $this->ServiceRequestModel->listing($status, 200);
        $this->ServiceRequestModel->settle_many($rows);                                  // a guest may have paid a link just now
        $rows = $this->ServiceRequestModel->listing($status, 200);
        $this->ok(['data' => array_map(fn($r) => $this->row($r), $rows), 'counts' => $this->ServiceRequestModel->counts(), 'services' => $this->ServiceRequestModel->services(),
            'online' => $this->paymongo->provider() === 'paymongo']);
    }

    public function add($ref)                        // POST: the front desk adds a request or an extra charge to a booking
    {
        $this->need('POST'); $this->who();
        $b = preg_match('/^HG-[A-Z0-9]{6}$/', $ref) ? $this->BookingModel->by_ref($ref) : null;
        if (!$b) $this->fail('Booking not found.', 404);
        $in = $this->body();
        $price = isset($in['price']) && $in['price'] !== '' ? filter_var((string) $in['price'], FILTER_VALIDATE_FLOAT) : null;
        if ($price === false) $this->fail('The price must be a number.', 422);
        [$ok, $res] = $this->ServiceRequestModel->create($b, (string) ($in['service'] ?? ''), (string) ($in['note'] ?? ''), 'staff', $price !== null && $price > 0 ? (float) $price : null);
        if (!$ok) $this->fail($res, 422);
        $this->log('request_added', $ref . ' · ' . trim((string) $in['service']));
        $this->ok(['data' => $this->row($this->request((int) $res))], 201);
    }

    public function decide($id)                      // PUT
    {
        $this->need('PUT'); $me = $this->who();
        $r = $this->request((int) $id); $in = $this->body();
        $price = isset($in['price']) && $in['price'] !== '' ? filter_var((string) $in['price'], FILTER_VALIDATE_FLOAT) : null;
        if ($price === false) $this->fail('The price must be a number.', 422);
        [$ok, $msg] = $this->ServiceRequestModel->decide($r, (string) ($in['do'] ?? ''), $price !== null ? (float) $price : null, (int) $me['id'], mb_substr(trim((string) ($in['reply'] ?? '')), 0, 200));
        if (!$ok) $this->fail($msg, 409);
        $this->log('request_' . (string) $in['do'], $r['booking_ref'] . ' · ' . $r['service']);
        $this->ok(['message' => $msg, 'data' => $this->row($this->request((int) $id))]);
    }

    public function pay($id)                         // POST
    {
        $this->need('POST'); $this->who();
        $r = $this->request((int) $id); $do = (string) ($this->body()['do'] ?? '');
        if (!in_array($do, ['cash', 'link', 'check'], true)) $this->fail('Unknown payment action.', 422);
        if ($do === 'cash') {
            [$ok, $msg] = $this->ServiceRequestModel->collect_cash($r);
            if (!$ok) $this->fail($msg, 409);
            $this->log('request_paid_cash', $r['booking_ref'] . ' · ' . $r['service']);
            $this->ok(['state' => 'paid', 'message' => $msg, 'data' => $this->row($this->request((int) $id))]);
        }
        if ($do === 'check') {
            $state = $this->ServiceRequestModel->settle($r);
            if ($state === 'paid') $this->log('request_paid_online', $r['booking_ref'] . ' · ' . $r['service']);
            $this->ok(['state' => $state, 'message' => $state === 'paid' ? 'Payment received.' : ($state === 'unreachable' ? 'Could not reach PayMongo. Try again.' : 'Not paid yet.'), 'data' => $this->row($this->request((int) $id))]);
        }
        [$url, $why] = $this->ServiceRequestModel->start_link($r);
        if (!$url) $this->fail($why ?: 'PayMongo did not create the payment link.', 422);
        $this->log('request_link', $r['booking_ref'] . ' · ' . $r['service']);
        $this->ok(['state' => 'pending', 'url' => $url, 'message' => 'Payment link ready.', 'data' => $this->row($this->request((int) $id))]);
    }
}
