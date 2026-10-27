<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| HOTELGO JSON API: real photos.
|  - public:  GET /api/photos       the home-page pictures (slot => image id); a picture is shown from /photo/{id}
|  - staff:   POST /api/manage/photos   upload or remove the picture of a hotel, a room type or a home-page slot
|      body: { kind: 'hotel' | 'room_type' | 'site', id, slot, data: 'data:image/jpeg;base64,...' }   (data: null removes the picture)
|      hotel / room type: the hotel owner for their own hotel, the platform admin for any;  site: platform admin only
*/
class ApiPhotoController extends \Hotelgo\Support\ApiBase
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model(['PhotoModel', 'HotelModel', 'RoomTypeModel']);
    }

    public function site()                           // GET public
    {
        $this->need('GET');
        $this->ok(['data' => (object) $this->PhotoModel->site_slots(), 'slots' => $this->slot_list()]);
    }

    private function slot_list(): array
    {
        $o = [];
        foreach (\PhotoModel::SLOTS as $k => $label) { $o[] = ['slot' => $k, 'label' => $label]; }
        return $o;
    }

    public function save()                           // POST staff
    {
        $this->need('POST');
        $b = $this->body();
        $kind = (string) ($b['kind'] ?? '');
        if (!in_array($kind, ['hotel', 'room_type', 'site'], true)) $this->fail('Choose what the picture is for.', 422);
        $this->who($kind === 'site' ? 'platform' : 'admin');

        $id = (int) ($b['id'] ?? 0); $slot = (string) ($b['slot'] ?? '');
        if ($kind === 'hotel') {
            $this->HotelModel->hotel($id) ?: $this->fail('Hotel not found.', 404);
        } elseif ($kind === 'room_type') {
            $this->RoomTypeModel->type($id) ?: $this->fail('Room type not found.', 404);
        } elseif (!isset(\PhotoModel::SLOTS[$slot])) {
            $this->fail('Unknown picture place.', 422);
        }

        $data = $b['data'] ?? null;
        $new = 0;
        if ($data !== null && $data !== '') {
            [$new, $why] = $this->PhotoModel->store((string) $data);
            if ($new < 1) $this->fail($why, 422);
        }
        $old = $this->PhotoModel->attach($kind, $id, $slot, $new);
        $this->PhotoModel->drop($old);                // the replaced picture is deleted, the database does not fill up
        $this->log($new ? 'photo_saved' : 'photo_removed', $kind . ($id ? ' #' . $id : '') . ($slot !== '' ? ' ' . $slot : ''));
        $this->ok(['image_id' => $new ?: null, 'message' => $new ? 'Photo saved.' : 'Photo removed.']);
    }
}
