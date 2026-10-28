<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| HOTELGO JSON API: Owner-only pages: payments, notifications, staff accounts, settings, reports, audit log.
| Shared helpers (token check, JSON replies) are in app/support/ApiBase.php. Business rules live in the models.
*/
class ApiOwnerController extends \Hotelgo\Support\ApiBase
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model(['PaymentModel', 'NotificationModel', 'SettingModel']);
    }

    public function payments()
    {
        $this->need('GET'); $this->who('admin');
        $this->ok(['data' => $this->PaymentModel->recent(200), 'paid_today' => $this->PaymentModel->total('paid', date('Y-m-d 00:00:00')),
            'refunded' => $this->PaymentModel->total('refunded'), 'waiting' => $this->PaymentModel->waiting_count()]);
    }

    public function payment_refund($id)
    {
        $this->need('POST'); $this->who('admin');
        [$ok, $msg] = $this->PaymentModel->refund((int) $id);
        $this->log($ok ? 'refund' : 'refund_failed', 'payment #' . (int) $id);
        $this->ok(['message' => $ok ? 'Payment refunded and the booking was cancelled.' : $msg], $ok ? 200 : 409);
    }

    public function notifications()
    {
        $this->need('GET'); $this->who('admin');
        $this->call->library('messenger');
        $this->ok(['data' => $this->NotificationModel->recent(200), 'services' => ['sms' => $this->messenger->sms_enabled(), 'email' => $this->messenger->email_enabled()]]);
    }

    public function notification_retry($id)
    {
        $this->need('POST'); $this->who('admin');
        $ok = $this->NotificationModel->retry((int) $id);
        $this->ok(['message' => $ok ? 'Sent again.' : 'Could not send again.'], $ok ? 200 : 409);
    }

    public function notification_delete($id)
    {
        $this->need('DELETE'); $this->who('admin');
        if (!$this->NotificationModel->remove((int) $id)) $this->fail('Notification not found.', 404);
        $this->ok(['message' => 'Notification deleted.']);
    }

    /* May the current owner manage this account? Front desk of their hotel, or (platform admin only) a hotel owner */
    private function managed(array $u): bool
    {
        if (!\Hotelgo\Support\Scope::allows($u['hotel_id']) || (int) $u['id'] === (int) $this->me['id']) return false;
        return $u['role'] === 'staff' || ($u['role'] === 'admin' && !empty($u['hotel_id']) && \Hotelgo\Support\Scope::platform());
    }

    public function staff()
    {
        $this->need('GET'); $this->who('admin');
        $this->ok(['data' => $this->UserModel->staff_list()]);
    }

    public function staff_create()
    {
        $this->need('POST'); $this->who('admin');
        $in = $this->body();
        [$ok, $msg, $temp, $id] = $this->UserModel->create_staff((string) ($in['full_name'] ?? ''), (string) ($in['email'] ?? ''), (string) ($in['mobile'] ?? ''), (int) ($in['hotel_id'] ?? 0), ($in['role'] ?? '') === 'owner' ? 'admin' : 'staff');
        if (!$ok) $this->fail($msg, 422);
        $this->log('staff_created', strtolower((string) $in['email']));
        $this->ok(['message' => 'Staff account created. The temporary password is shown only once.', 'temporary_password' => $temp, 'id' => $id], 201);
    }

    public function staff_update($id)                // PUT: name / mobile, or is_active
    {
        $this->need('PUT'); $me = $this->who('admin');
        $u = $this->UserModel->by_id((int) $id);
        if (!$u || !$this->managed($u)) $this->fail('Staff account not found.', 404);
        $in = $this->body(); $row = [];
        if (isset($in['full_name'])) {
            if (!len_ok(trim((string) $in['full_name']), 2, 120)) $this->fail('Enter the name.', 422);
            $row['full_name'] = trim((string) $in['full_name']);
        }
        if (isset($in['mobile'])) {
            $m = normalize_mobile((string) $in['mobile']);
            $other = $m ? $this->UserModel->by_mobile($m) : null;
            if (!$m || ($other && (int) $other['id'] !== (int) $u['id'])) $this->fail('Mobile number is invalid or already used.', 422);
            $row['mobile'] = $m;
        }
        if ($row) $this->UserModel->save_profile((int) $u['id'], $row);
        if (isset($in['is_active']) && (int) (bool) $in['is_active'] !== (int) $u['is_active']) $this->UserModel->toggle_staff((int) $u['id']);
        $this->log('staff_updated', '#' . (int) $id);
        $this->ok(['data' => $this->strip($this->UserModel->by_id((int) $id))]);
    }

    public function staff_delete($id)
    {
        $this->need('DELETE'); $this->who('admin');
        $u = $this->UserModel->by_id((int) $id);
        if (!$u || !$this->managed($u)) $this->fail('Staff account not found.', 404);
        [$ok, $why] = $this->UserModel->remove((int) $id);
        if (!$ok) $this->fail($why, 409);
        $this->log('staff_deleted', '#' . (int) $id);
        $this->ok(['message' => $why]);
    }

    public function settings()
    {
        $this->need('GET'); $this->who('platform');
        $this->call->library('integrations');
        $all = $this->SettingModel->all_values();
        $this->ok(['data' => array_intersect_key($all, SettingModel::RULES), 'integrations' => $this->integrations->status()]);
    }

    public function settings_save()
    {
        $this->need('PUT'); $this->who('platform');
        $err = $this->SettingModel->save_all($this->body());
        if ($err) $this->fail($err, 422);
        $this->log('settings_saved');
        $this->ok(['data' => array_intersect_key($this->SettingModel->all_values(), SettingModel::RULES)]);
    }

    public function reports()
    {
        $this->need('GET'); $this->who('admin');
        $q = $this->api->get_query_params();
        $ok = fn($d) => is_string($d) && ($x = DateTime::createFromFormat('!Y-m-d', $d)) && $x->format('Y-m-d') === $d;
        $to = $ok($q['to'] ?? null) ? $q['to'] : date('Y-m-d'); $from = $ok($q['from'] ?? null) ? $q['from'] : date('Y-m-d', strtotime('-6 days'));
        if ($from > $to) [$from, $to] = [$to, $from];
        if (strtotime($to) - strtotime($from) > 366 * 86400) $this->fail('Please choose at most one year.', 422);
        $days = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
        $prev = $this->PaymentModel->report(date('Y-m-d', strtotime($from . ' -' . $days . ' days')), date('Y-m-d', strtotime($from . ' -1 day')));
        $this->ok($this->PaymentModel->report($from, $to) + ['prev_total' => $prev['total']]);
    }

    public function audit()
    {
        $this->need('GET'); $this->who('admin');
        $this->ok(['data' => $this->AuditModel->recent(300)]);
    }
}
