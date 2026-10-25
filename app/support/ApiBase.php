<?php
namespace Hotelgo\Support;

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Shared parts of every HOTELGO JSON API controller (ApiAuthController, ApiBookingController, ...).
| Built on LavaLust's Api library: JWT access token (15 min) + refresh token (7 days, stored hashed in refresh_tokens).
| Routes live under /api (see app/config/routes.php). No cookies and no CSRF token: the bearer token proves who is calling.
|
|   public : availability, room types, sign in / refresh / sign out
|   staff  : bookings, guests, dashboard, own account
|   admin  : room types, rooms, staff accounts, settings, payments, notifications, reports, audit log
*/
abstract class ApiBase extends \Controller
{
    protected $me = null;                 // the signed-in user (re-read from the database on every request)

    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->model(['UserModel', 'AuditModel']);
        $this->api->rate_limit('api:' . client_ip());
    }

    /* ---------------- small helpers ---------------- */
    protected function body(): array
    {
        return $this->api->body();
    }
    protected function ok($data = [], int $code = 200): void
    {
        $this->api->respond($data, $code);
    }
    protected function fail(string $msg, int $code = 400): void
    {
        $this->api->respond_error($msg, $code);
    }
    protected function need(string $method): void
    {
        $this->api->require_method($method);
    }
    protected function who(string $min = 'staff', bool $allowTemp = false): array
    {
        if ($this->me === null) {
            $payload = $this->api->require_jwt();
            $u = $this->UserModel->session_user((int) $payload['sub']);        // also checks is_active
            if (!$u || !in_array($u['role'], ['staff', 'admin'], true)) {
                $this->fail('Unauthorized', 401);
            }
            $this->me = $u;
        }
        if ((int) $this->me['must_change_password'] === 1 && !$allowTemp) {
            $this->fail('Please change your temporary password first.', 403);
        }
        if ($min === 'admin' && $this->me['role'] !== 'admin') {
            $this->fail('Only the owner can do this.', 403);
        }
        return $this->me;
    }
    protected function log(string $action, string $detail = ''): void
    {
        $this->AuditModel->record($this->me['id'] ?? null, $action, $detail);
    }
    protected function publicUser(array $u): array
    {
        return ['id' => (int) $u['id'], 'full_name' => $u['full_name'], 'email' => $u['email'], 'mobile' => $u['mobile'],
            'role' => $u['role'], 'must_change_password' => (int) $u['must_change_password']];
    }
    protected function strip(array $row, array $drop = ['password_hash']): array
    {
        return array_diff_key($row, array_flip($drop));
    }
}
