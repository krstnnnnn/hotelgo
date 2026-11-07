<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| HOTELGO JSON API: What the motel sells: public availability + room types, and the owner's room types and rooms.
| Shared helpers (token check, JSON replies) are in app/support/ApiBase.php. Business rules live in the models.
*/
class ApiCatalogController extends \Hotelgo\Support\ApiBase
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model(['RoomTypeModel', 'RoomModel', 'BookingModel', 'ReviewModel', 'CouponModel']);
    }

    public function room_types()
    {
        $this->need('GET');
        $q = $this->api->get_query_params();
        $this->ok(['data' => $this->RoomTypeModel->types(true, (int) ($q['hotel'] ?? 0), (string) ($q['area'] ?? '')),
                   'overnight' => ['start' => setting('overnight_start', '20:00'), 'end' => setting('overnight_end', '12:00')],
                   'daily' => ['checkin' => setting('daily_checkin', '14:00'), 'checkout' => setting('daily_checkout', '12:00'), 'max_days' => max_stay_days()]]);
    }

    public function availability()
    {
        $this->need('GET');
        $q = $this->api->get_query_params();
        $hours = isset($q['hours']) && ctype_digit((string) $q['hours']) ? (int) $q['hours'] : 3;
        if (($q['stay'] ?? '') === 'daily') $hours = (isset($q['days']) ? (ctype_digit((string) $q['days']) ? (int) $q['days'] : 0) : 1) * 24;      // a stay of several days: the guest chooses how many (not given = 1; not a number = refused)
        $guests = isset($q['guests']) && ctype_digit((string) $q['guests']) ? max(1, (int) $q['guests']) : 1;
        // Short cache: the same search (same date, time, guests, hotel) is answered from a file for 60 seconds.
        // The room list and vouchers are the same for every visitor, so one saved answer can serve them all.
        // A booking still checks the room when it is made, so a room taken in the last minute is refused there.
        $norm = $q; ksort($norm);
        $cacheFile = ROOT_DIR . 'runtime/cache/avail_' . sha1(json_encode($norm)) . '.json';
        if (is_file($cacheFile) && time() - filemtime($cacheFile) < 60) {
            $saved = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($saved)) $this->ok($saved);
        }
        $this->BookingModel->expire_holds();
        $w = $this->BookingModel->window((string) ($q['stay'] ?? ''), (string) ($q['date'] ?? ''), (string) ($q['time'] ?? '19:00'), $hours);
        if (!$w) {
            $this->fail('Choose a valid stay type, date and start time (not in the past, within 60 days; day stays up to ' . max_stay_days() . ' days).', 422);
        }
        $hotel = (int) ($q['hotel'] ?? 0); $area = (string) ($q['area'] ?? '');
        $types = $this->RoomTypeModel->types(true, $hotel, $area);
        $avail = $this->BookingModel->availability($w['start'], $w['end'], $hotel, $area, $types);
        $rows = [];
        $sums = $this->ReviewModel->summaries(array_map(fn($t) => (int) $t['hotel_id'], $types));        // the rating of every hotel, in one query
        $recent = $this->BookingModel->recent_bookings(array_map(fn($t) => (int) $t['id'], $types));      // "someone booked N hours ago"
        foreach ($types as $t) {
            if ((int) $t['max_guests'] < $guests) continue;
            if ($q['stay'] === 'daily' && (float) $t['price_daily'] <= 0) continue;         // this room type has no day stays
            $t['free']  = $avail[(int) $t['id']] ?? 0;
            $t['price'] = $this->BookingModel->price($t, (string) $q['stay'], (int) $w['hours']);
            $t['rating'] = $sums[(int) $t['hotel_id']] ?? ReviewModel::EMPTY;
            $t['booked_hours_ago'] = $recent[(int) $t['id']] ?? null;
            $best = $this->CouponModel->best(0, (int) $t['hotel_id'], (float) $t['price'], $w['start']);   // a voucher anyone can use (no sign-in needed)
            $t['voucher'] = $best ? ['code' => $best['coupon']['code'], 'title' => $best['coupon']['title'], 'discount' => $best['discount'],
                'final' => round((float) $t['price'] - $best['discount'], 2)] : null;
            $rows[] = $t;
        }
        $payload = ['window' => $w, 'days' => $q['stay'] === 'daily' ? intdiv((int) $w['hours'], 24) : 0, 'data' => $rows];
        @file_put_contents($cacheFile, json_encode($payload), LOCK_EX);
        $this->ok($payload);
    }

    public function types()                          // GET all (including hidden ones)
    {
        $this->need('GET'); $this->who();
        $this->ok(['data' => $this->RoomTypeModel->types(false)]);
    }

    public function type_create()
    {
        $this->need('POST'); $this->who('admin');
        $d = $this->RoomTypeModel->clean($this->body()) ?: $this->fail('Please check the name, guests and prices (numbers only).', 422);
        $id = $this->RoomTypeModel->save(null, $d);
        $this->log('room_type_created', $d['name']);
        $this->ok(['data' => $this->RoomTypeModel->type($id)], 201);
    }

    public function type_update($id)
    {
        $this->need('PUT'); $this->who('admin');
        $cur = $this->RoomTypeModel->type((int) $id) ?: $this->fail('Room type not found.', 404);
        $d = $this->RoomTypeModel->clean($this->body() + $cur) ?: $this->fail('Please check the name, guests and prices (numbers only).', 422);
        $this->RoomTypeModel->save((int) $id, $d);
        $this->log('room_type_updated', $d['name']);
        $this->ok(['data' => $this->RoomTypeModel->type((int) $id)]);
    }

    public function type_delete($id)
    {
        $this->need('DELETE'); $this->who('admin');
        [$ok, $why] = $this->RoomTypeModel->remove((int) $id);
        if (!$ok) $this->fail($why, 409);
        $this->log('room_type_deleted', '#' . (int) $id);
        $this->ok(['message' => $why]);
    }

    public function rooms()
    {
        $this->need('GET'); $this->who();
        $this->ok(['data' => $this->RoomModel->rooms()]);
    }

    public function room_create()
    {
        $this->need('POST'); $this->who('admin');
        [$d, $why] = $this->RoomModel->clean_room($this->body());
        if (!$d) $this->fail($why, 422);
        $id = $this->RoomModel->add_room($d['room_no'], $d['room_type_id'], $d['floor']);
        $this->RoomModel->save_room($id, ['status' => $d['status']]);
        $this->log('room_added', $d['room_no']);
        $this->ok(['data' => $this->RoomModel->room($id)], 201);
    }

    public function room_update($id)
    {
        $this->need('PUT'); $this->who('admin');
        $cur = $this->RoomModel->room((int) $id) ?: $this->fail('Room not found.', 404);
        [$d, $why] = $this->RoomModel->clean_room($this->body() + $cur, (int) $id);
        if (!$d) $this->fail($why, 422);
        $this->RoomModel->save_room((int) $id, $d);
        $this->log('room_updated', $d['room_no']);
        $this->ok(['data' => $this->RoomModel->room((int) $id)]);
    }

    public function room_delete($id)
    {
        $this->need('DELETE'); $this->who('admin');
        [$ok, $why] = $this->RoomModel->remove((int) $id);
        if (!$ok) $this->fail($why, 409);
        $this->log('room_deleted', '#' . (int) $id);
        $this->ok(['message' => $why]);
    }
}
