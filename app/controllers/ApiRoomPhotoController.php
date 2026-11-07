<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Room type details and the room photo gallery.
|   GET    /api/room-types/{id}/details            public: the room, the hotel (for the map), its pictures, rating and reviews
|   POST   /api/types/{id}/photos                  owner: add a picture to the gallery  (body: data = data:image/jpeg;base64,...)
|   DELETE /api/types/{id}/photos/{image}          owner: take a picture out of the gallery
*/
class ApiRoomPhotoController extends \Hotelgo\Support\ApiBase
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model(['RoomTypeModel', 'RoomPhotoModel', 'PhotoModel', 'HotelModel', 'ReviewModel']);
    }

    public function details($id)
    {
        $this->need('GET');
        $t = $this->RoomTypeModel->type((int) $id) ?: $this->fail('This room type is not on HOTELGO.', 404);
        $hotel = $this->HotelModel->find((int) $t['hotel_id']);
        if (!$hotel || !(int) $hotel['is_active'] || !(int) $t['is_active']) $this->fail('This room type is not available.', 404);
        $photos = $this->RoomPhotoModel->images_of((int) $id, (int) ($t['image_id'] ?? 0));
        if (!$photos && !empty($hotel['image_id'])) $photos = [(int) $hotel['image_id']];        // no room pictures yet: show the hotel's
        $this->ok([
            'type' => array_intersect_key($t, array_flip(['id', 'name', 'description', 'amenities', 'max_guests', 'price_3h', 'price_6h', 'price_12h', 'price_overnight', 'price_daily', 'is_vip', 'included_services'])),
            'hotel' => ['id' => (int) $hotel['id'], 'name' => $hotel['name'], 'area' => $hotel['area'], 'address' => $hotel['address'], 'lat' => (float) $hotel['lat'], 'lng' => (float) $hotel['lng']],
            'photos' => $photos,
            'rating' => $this->ReviewModel->summary((int) $hotel['id']),
            'reviews' => $this->ReviewModel->recent((int) $hotel['id'], 20),
        ]);
    }

    public function photo_add($id)
    {
        $this->need('POST'); $this->who('admin');
        $t = $this->RoomTypeModel->type((int) $id) ?: $this->fail('Room type not found.', 404);
        $b = $this->body();
        [$imageId, $err] = $this->PhotoModel->store((string) ($b['data'] ?? ''));
        if (!$imageId) $this->fail($err ?: 'The picture could not be saved.', 422);
        $this->RoomPhotoModel->add((int) $id, (int) $imageId);
        $this->log('room_photo_added', $t['name'] . ' #' . $imageId);
        $this->ok(['image_id' => (int) $imageId, 'photos' => $this->RoomPhotoModel->images_of((int) $id, (int) ($t['image_id'] ?? 0))], 201);
    }

    public function photo_remove($id, $image)
    {
        $this->need('DELETE'); $this->who('admin');
        $t = $this->RoomTypeModel->type((int) $id) ?: $this->fail('Room type not found.', 404);
        $this->RoomPhotoModel->remove((int) $id, (int) $image);
        $this->log('room_photo_removed', $t['name'] . ' #' . (int) $image);
        $this->ok(['photos' => $this->RoomPhotoModel->images_of((int) $id, (int) ($t['image_id'] ?? 0))]);
    }
}
