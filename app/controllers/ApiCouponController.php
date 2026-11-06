<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| HOTELGO JSON API: coupons (promo codes).
|   GET    /api/offers                 public: the offers shown on the website (active, in their dates, not used up)
|   GET    /api/coupons                owner: the coupons of the hotel (the platform admin sees all) with how many times each was used
|   POST   /api/coupons                owner: create one
|   PUT    /api/coupons/{id}           owner: change one
|   DELETE /api/coupons/{id}           owner: delete one that was never used on a booking
| A guest types the code at checkout (see ApiGuestBookingController); the front desk can type it on a walk-in.
*/
class ApiCouponController extends \Hotelgo\Support\ApiBase
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model(['CouponModel']);
    }

    public function offers()
    {
        $this->need('GET');
        $this->ok(['data' => $this->CouponModel->offers()]);
    }

    public function coupons()
    {
        $this->need('GET'); $this->who('admin');
        $this->ok(['data' => $this->CouponModel->all()]);
    }

    public function create()
    {
        $this->need('POST'); $this->who('admin');
        [$d, $why] = $this->CouponModel->clean($this->body());
        if (!$d) $this->fail($why, 422);
        $id = $this->CouponModel->save(null, $d);
        $this->log('coupon_created', $d['code']);
        $this->ok(['data' => $this->CouponModel->one($id)], 201);
    }

    public function update($id)
    {
        $this->need('PUT'); $this->who('admin');
        $cur = $this->CouponModel->one((int) $id) ?: $this->fail('Coupon not found.', 404);
        [$d, $why] = $this->CouponModel->clean($this->body() + $cur, (int) $id);
        if (!$d) $this->fail($why, 422);
        $this->CouponModel->save((int) $id, $d);
        $this->log('coupon_updated', $d['code']);
        $this->ok(['data' => $this->CouponModel->one((int) $id)]);
    }

    public function delete($id)
    {
        $this->need('DELETE'); $this->who('admin');
        [$ok, $why] = $this->CouponModel->remove((int) $id);
        if (!$ok) $this->fail($why, 409);
        $this->log('coupon_deleted', '#' . (int) $id);
        $this->ok(['message' => $why]);
    }
}
