<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Guest ratings and saved hotels.
|   GET  /api/hotels/{id}/reviews           public: the average, its word and the latest comments
|   POST /api/guest/bookings/{ref}/review   a signed-in guest rates their own stay that has ended (once)
|   GET  /api/guest/favorites               the hotel ids this guest saved
|   POST /api/guest/favorites/{id}          save or un-save a hotel (the heart)
*/
class ApiReviewController extends \Hotelgo\Support\ApiBase
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model(['BookingModel', 'ReviewModel', 'FavoriteModel', 'HotelModel']);
    }

    public function hotel_reviews($id)
    {
        $this->need('GET');
        $hotel = $this->HotelModel->find((int) $id);
        if (!$hotel || !(int) $hotel['is_active']) $this->fail('This hotel is not on HOTELGO.', 404);
        $this->ok(['summary' => $this->ReviewModel->summary((int) $id), 'data' => $this->ReviewModel->recent((int) $id, 10)]);
    }

    public function review_post($ref)
    {
        $this->need('POST'); $me = $this->member();
        $b = preg_match('/^HG-[A-Z0-9]{6}$/', (string) $ref) ? $this->BookingModel->find_mine($ref, (int) $me['id']) : null;
        if (!$b) $this->fail('We could not find that booking.', 404);
        $in = $this->body();
        $res = $this->ReviewModel->submit((int) $me['id'], $b, (int) ($in['rating'] ?? 0), (string) ($in['comment'] ?? ''));
        if (!$res['ok']) $this->fail($res['error'], 422);
        $this->log('review_created', $ref);
        $this->ok(['message' => 'Thank you! Your rating is now shown on the hotel page.', 'id' => $res['id']], 201);
    }

    public function favorites()
    {
        $this->need('GET'); $me = $this->member();
        $this->ok(['data' => $this->FavoriteModel->hotel_ids((int) $me['id'])]);
    }

    public function favorite_toggle($id)
    {
        $this->need('POST'); $me = $this->member();
        $hotel = $this->HotelModel->find((int) $id);
        if (!$hotel || !(int) $hotel['is_active']) $this->fail('This hotel is not on HOTELGO.', 404);
        $on = $this->FavoriteModel->toggle((int) $me['id'], (int) $id);
        $this->ok(['hotel_id' => (int) $id, 'favorite' => $on]);
    }
}
