<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| HOTELGO JSON API: Guest records.
| Shared helpers (token check, JSON replies) are in app/support/ApiBase.php. Business rules live in the models.
*/
class ApiGuestController extends \Hotelgo\Support\ApiBase
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model(['BookingModel']);
    }

    public function guests()
    {
        $this->need('GET'); $this->who();
        $q = $this->api->get_query_params();
        $this->ok(['data' => $this->UserModel->guests((string) ($q['q'] ?? ''))]);
    }

    public function guest($id)
    {
        $this->need('GET'); $this->who();
        $g = $this->UserModel->guest((int) $id) ?: $this->fail('Guest not found.', 404);
        $this->log('view_guest', 'user #' . $g['id']);
        $this->ok(['data' => $g, 'bookings' => $this->BookingModel->for_user((int) $g['id'])]);
    }

    public function guest_update($id)                // PUT: name / email / mobile / staff note
    {
        $this->need('PUT'); $this->who();
        $g = $this->UserModel->guest((int) $id) ?: $this->fail('Guest not found.', 404);
        $in = $this->body(); $row = [];
        if (isset($in['full_name'])) {
            if (!len_ok(trim((string) $in['full_name']), 2, 120)) $this->fail('Enter the name.', 422);
            $row['full_name'] = trim((string) $in['full_name']);
        }
        if (isset($in['email'])) {
            $e = strtolower(trim((string) $in['email']));
            $other = $this->UserModel->by_email($e);
            if (!filter_var($e, FILTER_VALIDATE_EMAIL) || ($other && (int) $other['id'] !== (int) $g['id'])) $this->fail('Email is invalid or already used.', 422);
            $row['email'] = $e;
        }
        if (isset($in['mobile'])) {
            $m = normalize_mobile((string) $in['mobile']);
            $other = $m ? $this->UserModel->by_mobile($m) : null;
            if (!$m || ($other && (int) $other['id'] !== (int) $g['id'])) $this->fail('Mobile number is invalid or already used.', 422);
            $row['mobile'] = $m;
        }
        if ($row) $this->UserModel->save_profile((int) $g['id'], $row);
        if (isset($in['staff_note'])) $this->UserModel->save_note((int) $g['id'], (string) $in['staff_note']);
        $this->log('guest_updated', 'user #' . $g['id']);
        $this->ok(['data' => $this->UserModel->guest((int) $g['id'])]);
    }

    public function guest_delete($id)
    {
        $this->need('DELETE'); $this->who('platform');
        $g = $this->UserModel->guest((int) $id) ?: $this->fail('Guest not found.', 404);
        [$ok, $why] = $this->UserModel->remove((int) $g['id']);
        if (!$ok) $this->fail($why, 409);
        $this->log('guest_deleted', 'user #' . $g['id']);
        $this->ok(['message' => $why]);
    }
}
