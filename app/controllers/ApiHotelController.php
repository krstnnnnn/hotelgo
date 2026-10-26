<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| HOTELGO JSON API: the hotels on the platform.
|  - public:  the list of hotels (for the landing page and the map) and the areas
|  - staff:   the platform admin adds / edits / hides hotels; a hotel owner edits only their own hotel profile
| Shared helpers (token check, JSON replies) are in app/support/ApiBase.php. Business rules live in HotelModel.
*/
class ApiHotelController extends \Hotelgo\Support\ApiBase
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model(['HotelModel']);
    }

    public function hotels()                         // GET public: active hotels that have rooms to sell
    {
        $this->need('GET');
        $q = $this->api->get_query_params();
        $this->ok(['data' => $this->HotelModel->listing((string) ($q['area'] ?? '')), 'areas' => $this->HotelModel->areas()]);
    }

    public function manage()                         // GET staff: all hotels (platform admin) or the own hotel (hotel owner / front desk)
    {
        $this->need('GET'); $this->who();
        $this->ok(['data' => $this->HotelModel->all()]);
    }

    public function create()                         // POST platform admin
    {
        $this->need('POST'); $this->who('platform');
        [$d, $why] = $this->HotelModel->clean($this->body());
        if (!$d) $this->fail($why, 422);
        $id = $this->HotelModel->save(null, $d);
        $this->log('hotel_created', $d['name']);
        $this->ok(['data' => $this->HotelModel->hotel($id)], 201);
    }

    public function update($id)                      // PUT platform admin (any hotel) or the owner of that hotel
    {
        $this->need('PUT'); $this->who('admin');
        $cur = $this->HotelModel->hotel((int) $id) ?: $this->fail('Hotel not found.', 404);
        [$d, $why] = $this->HotelModel->clean($this->body() + $cur, (int) $id);
        if (!$d) $this->fail($why, 422);
        $this->HotelModel->save((int) $id, $d);
        $this->log('hotel_updated', $d['name']);
        $this->ok(['data' => $this->HotelModel->hotel((int) $id)]);
    }

    public function delete($id)                      // DELETE platform admin (only a hotel with no rooms, staff or bookings)
    {
        $this->need('DELETE'); $this->who('platform');
        [$ok, $why] = $this->HotelModel->remove((int) $id);
        if (!$ok) $this->fail($why, 409);
        $this->log('hotel_deleted', '#' . (int) $id);
        $this->ok(['message' => $why]);
    }
}
