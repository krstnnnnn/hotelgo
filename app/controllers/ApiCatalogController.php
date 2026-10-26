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
        $this->call->model(['RoomTypeModel', 'RoomModel', 'BookingModel']);
    }

    public function room_types()
    {
        $this->need('GET');
        $q = $this->api->get_query_params();
        $this->ok(['data' => $this->RoomTypeModel->types(true, (int) ($q['hotel'] ?? 0), (string) ($q['area'] ?? ''))]);
    }

    public function availability()
    {
        $this->need('GET');
        $q = $this->api->get_query_params();
        $hours = isset($q['hours']) && ctype_digit((string) $q['hours']) ? (int) $q['hours'] : 3;
        $guests = isset($q['guests']) && ctype_digit((string) $q['guests']) ? max(1, (int) $q['guests']) : 1;
        $this->BookingModel->expire_holds();
        $w = $this->BookingModel->window((string) ($q['stay'] ?? ''), (string) ($q['date'] ?? ''), (string) ($q['time'] ?? '19:00'), $hours);
        if (!$w) {
            $this->fail('Choose a valid stay type, date and start time (not in the past, within 60 days).', 422);
        }
        $hotel = (int) ($q['hotel'] ?? 0); $area = (string) ($q['area'] ?? '');
        $avail = $this->BookingModel->availability($w['start'], $w['end'], $hotel, $area);
        $rows = [];
        foreach ($this->RoomTypeModel->types(true, $hotel, $area) as $t) {
            if ((int) $t['max_guests'] < $guests) continue;
            $t['free']  = $avail[(int) $t['id']] ?? 0;
            $t['price'] = $this->BookingModel->price($t, (string) $q['stay'], (int) $w['hours']);
            $rows[] = $t;
        }
        $this->ok(['window' => $w, 'data' => $rows]);
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
