<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| HOTELGO JSON API: Sign in, refresh, sign out and the signed-in staff member's own account.
| Shared helpers (token check, JSON replies) are in app/support/ApiBase.php. Business rules live in the models.
*/
class ApiAuthController extends \Hotelgo\Support\ApiBase
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model(['LoginAttemptModel']);
    }

    public function login()
    {
        $this->need('POST');
        $in = $this->body();
        $email = strtolower((string) ($in['email'] ?? '')); $pass = (string) ($in['password'] ?? '');
        if ($this->LoginAttemptModel->too_many($email)) {
            $this->fail('Too many wrong attempts. Please wait 15 minutes and try again.', 429);
        }
        $u = $this->UserModel->by_email($email);
        $hash = $u['password_hash'] ?? '$2y$10$KWVj55JT.MtZUELFuZGH3OxA3815e58s7wtmIJFWHbtyqiJxN705O';   // same timing for unknown emails
        $ok = password_verify($pass, $hash) && $u && (int) $u['is_active'] === 1 && in_array($u['role'], ['staff', 'admin'], true);
        $this->LoginAttemptModel->record($email, $ok);
        if (!$ok) {
            usleep(400000);
            $this->fail('Email or password is incorrect.', 401);
        }
        $this->UserModel->touch_login((int) $u['id']);
        $this->AuditModel->record((int) $u['id'], 'api_login', $u['role']);
        $tokens = $this->api->issue_tokens(['id' => (int) $u['id'], 'role' => $u['role'], 'scopes' => $u['role'] === 'admin' ? ['read', 'write', 'delete'] : ['read', 'write']]);
        $this->ok(['tokens' => $tokens, 'user' => $this->publicUser($this->UserModel->session_user((int) $u['id']) ?: $u)]);
    }

    public function refresh()
    {
        $this->need('POST');
        $t = (string) ($this->body()['refresh_token'] ?? '');
        $this->api->refresh_access_token($t);          // answers by itself (new tokens, or an error)
    }

    public function logout()
    {
        $this->need('POST');
        $t = (string) ($this->body()['refresh_token'] ?? '');
        if ($t !== '') {
            $this->api->revoke_refresh_token($t);
        }
        $this->ok(['message' => 'Signed out.']);
    }

    public function me()
    {
        $this->need('GET');
        $this->ok(['user' => $this->publicUser($this->who('staff', true))]);
    }

    public function change_password()
    {
        $this->need('POST');
        $me = $this->who('staff', true);
        $in = $this->body();
        $u = $this->UserModel->by_id((int) $me['id']);
        $new = (string) ($in['password'] ?? '');
        if (!password_verify((string) ($in['current'] ?? ''), $u['password_hash'])) {
            $this->fail('Your current password is wrong.', 422);
        }
        if (!$this->UserModel->password_ok($new)) {
            $this->fail('New password must be 8-72 characters with letters and numbers.', 422);
        }
        $this->UserModel->set_password((int) $u['id'], $new);
        $this->log('password_changed');
        $this->ok(['message' => 'Password updated.']);
    }
}
