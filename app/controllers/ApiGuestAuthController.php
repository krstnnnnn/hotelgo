<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| HOTELGO JSON API: the GUEST account (the website visitor who books a room). Staff sign in somewhere else (/api/auth/login).
|   POST /api/guest/register        { full_name, email, mobile, password, password2, agree }   > a code is sent, verify next
|   POST /api/guest/verify          { email, code }                                           > signed in (tokens)
|   POST /api/guest/verify/resend   { email }
|   POST /api/guest/login           { email, password }                                       > tokens (or 403 need_verify)
|   POST /api/guest/forgot          { email }                                                 > a reset code is sent
|   POST /api/guest/reset           { email, code, password, password2 }
|   GET  /api/guest/me             POST /api/guest/password { current, password }
| Refresh and sign out use the same /api/auth/refresh and /api/auth/logout as the staff.
| The codes are sent by email only (EMAIL_PROVIDER must be set up); they are never returned by the API.
*/
class ApiGuestAuthController extends \Hotelgo\Support\ApiBase
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model(['LoginAttemptModel', 'OtpModel', 'NotificationModel']);
    }

    private function tokens(array $u): array
    {
        return $this->api->issue_tokens(['id' => (int) $u['id'], 'role' => 'guest', 'scopes' => ['read', 'write']]);
    }

    /* sends the code by email (it is never shown on screen or returned by the API) */
    private function send_code(array $u, string $purpose): void
    {
        $code = $this->OtpModel->issue((int) $u['id'], $purpose);
        $this->NotificationModel->code((int) $u['id'], $u['email'], $purpose, $code);
    }
    private function need_email(): void
    {
        if (!$this->NotificationModel->email_enabled()) $this->fail('Sign-up is not open yet because the email service is not set up. Please contact the motel.', 503);
    }
    private function pending(string $email): ?array        // an unverified guest account
    {
        $u = $this->UserModel->by_email(strtolower(trim($email)));
        return ($u && $u['role'] === 'guest' && (int) $u['is_active'] === 1 && empty($u['mobile_verified_at'])) ? $u : null;
    }

    public function register()
    {
        $this->need('POST'); $this->need_email();
        $in = $this->body();
        $name = trim((string) ($in['full_name'] ?? '')); $email = strtolower(trim((string) ($in['email'] ?? '')));
        $mobile = normalize_mobile(trim((string) ($in['mobile'] ?? ''))); $pass = (string) ($in['password'] ?? '');
        $regKey = 'register:' . client_ip();                      // max 5 new accounts per IP every 15 minutes
        if ($this->LoginAttemptModel->too_many($regKey)) $this->fail('Too many sign-ups from this network. Please try again in 15 minutes.', 429);
        $err = [];
        if (!len_ok($name, 2, 120)) $err[] = 'Please enter your full name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !len_ok($email, 5, 150)) $err[] = 'Please enter a valid email address.';
        if (!$mobile) $err[] = 'Please enter a valid Philippine mobile number (09XX XXX XXXX).';
        if (!$this->UserModel->password_ok($pass)) $err[] = 'Password must be 8-72 characters with at least one letter and one number.';
        if ($pass !== (string) ($in['password2'] ?? $pass)) $err[] = 'The two passwords do not match.';
        if (empty($in['agree'])) $err[] = 'Please accept the house rules and privacy notice.';
        if (!$err && ($this->UserModel->by_email($email) || $this->UserModel->by_mobile($mobile))) $err[] = 'An account with that email or mobile number already exists.';
        if ($err) $this->fail(implode(' ', $err), 422);

        $this->LoginAttemptModel->record($regKey, false);
        $id = $this->UserModel->create($name, $email, $mobile, $pass);
        $this->send_code($this->UserModel->by_id($id), 'verify');
        $this->ok(['pending' => true, 'email' => $email, 'message' => 'We sent a 6-digit code to your email.'], 201);
    }

    public function verify()
    {
        $this->need('POST');
        $in = $this->body();
        $u = $this->pending((string) ($in['email'] ?? ''));
        if (!$u || !$this->OtpModel->check((int) $u['id'], 'verify', preg_replace('/\D/', '', (string) ($in['code'] ?? '')))) {
            $this->fail('That code is wrong or has expired.', 422);
        }
        $this->UserModel->mark_verified((int) $u['id']);
        $this->UserModel->touch_login((int) $u['id']);
        $this->AuditModel->record((int) $u['id'], 'guest_verified', '');
        $this->ok(['tokens' => $this->tokens($u), 'user' => $this->publicUser($this->UserModel->session_user((int) $u['id']) ?: $u)]);
    }

    public function resend()
    {
        $this->need('POST');
        $u = $this->pending((string) ($this->body()['email'] ?? ''));
        if (!$u) $this->fail('There is nothing to verify for that email.', 404);
        if (!$this->OtpModel->can_issue((int) $u['id'], 'verify')) $this->fail('Please wait a moment before asking for another code.', 429);
        $this->need_email(); $this->send_code($u, 'verify');
        $this->ok(['message' => 'A new code was sent.']);
    }

    public function login()
    {
        $this->need('POST');
        $in = $this->body();
        $email = strtolower(trim((string) ($in['email'] ?? ''))); $pass = (string) ($in['password'] ?? '');
        if ($this->LoginAttemptModel->too_many($email)) $this->fail('Too many wrong attempts. Please wait 15 minutes and try again.', 429);
        $u = $this->UserModel->by_email($email);
        $hash = $u['password_hash'] ?? '$2y$10$KWVj55JT.MtZUELFuZGH3OxA3815e58s7wtmIJFWHbtyqiJxN705O';   // same timing for unknown emails
        $ok = password_verify($pass, $hash) && $u && (int) $u['is_active'] === 1 && $u['role'] === 'guest';      // staff use their own sign-in
        $this->LoginAttemptModel->record($email, $ok);
        if (!$ok) { usleep(400000); $this->fail('Email or password is incorrect.', 401); }
        if (empty($u['mobile_verified_at'])) {
            $this->need_email(); $this->send_code($u, 'verify');
            $this->ok(['error' => 'Please verify your account first. We sent you a code.', 'need_verify' => true, 'email' => $email], 403);
        }
        $this->UserModel->touch_login((int) $u['id']);
        $this->AuditModel->record((int) $u['id'], 'guest_login', '');
        $this->ok(['tokens' => $this->tokens($u), 'user' => $this->publicUser($this->UserModel->session_user((int) $u['id']) ?: $u)]);
    }

    public function forgot()
    {
        $this->need('POST');
        $email = strtolower(trim((string) ($this->body()['email'] ?? '')));
        $this->need_email();
        $u = $this->UserModel->by_email($email);
        if ($u && $u['role'] === 'guest' && (int) $u['is_active'] === 1 && !$this->LoginAttemptModel->too_many($email) && $this->OtpModel->can_issue((int) $u['id'], 'reset')) {
            $this->LoginAttemptModel->record($email, false);        // counts toward the limit so nobody can spam codes
            $this->send_code($u, 'reset');
        }
        $this->ok(['message' => 'If that account exists, we sent a reset code to its email address.']);   // same message either way
    }

    public function reset()
    {
        $this->need('POST');
        $in = $this->body();
        $u = $this->UserModel->by_email(strtolower(trim((string) ($in['email'] ?? ''))));
        $pass = (string) ($in['password'] ?? '');
        if (!$u || $u['role'] !== 'guest' || !$this->UserModel->password_ok($pass) || $pass !== (string) ($in['password2'] ?? $pass)) {
            $this->fail('Please check your code and use a strong password (8+ characters, letters and numbers).', 422);
        }
        if (!$this->OtpModel->check((int) $u['id'], 'reset', preg_replace('/\D/', '', (string) ($in['code'] ?? '')))) {
            $this->fail('That code is wrong or has expired.', 422);
        }
        $this->UserModel->set_password((int) $u['id'], $pass);
        $this->AuditModel->record((int) $u['id'], 'guest_password_reset', '');
        $this->ok(['message' => 'Password changed. You can sign in now.']);
    }

    public function me()
    {
        $this->need('GET');
        $this->ok(['user' => $this->publicUser($this->member())]);
    }

    public function password()
    {
        $this->need('POST');
        $me = $this->member(); $in = $this->body();
        $u = $this->UserModel->by_id((int) $me['id']); $new = (string) ($in['password'] ?? '');
        if (!password_verify((string) ($in['current'] ?? ''), $u['password_hash'])) $this->fail('Your current password is wrong.', 422);
        if (!$this->UserModel->password_ok($new)) $this->fail('New password must be 8-72 characters with letters and numbers.', 422);
        $this->UserModel->set_password((int) $u['id'], $new);
        $this->log('password_changed');
        $this->ok(['message' => 'Password updated.']);
    }
}
