<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class AuthController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model('UserModel');
        $this->call->model('NotificationModel');
    }

    /* ---------------- login ---------------- */
    public function login_form() { render('auth/login', ['staff' => false, 'title' => 'Sign in']); }
    public function staff_login_form() { render('auth/login', ['staff' => true, 'title' => 'Staff sign in']); }
    public function login() { $this->do_login(false); }
    public function staff_login() { $this->do_login(true); }

    private function do_login(bool $staff): void
    {
        $email = strtolower(post_str('email')); $pass = post_str('password');
        if ($this->UserModel->too_many_attempts($email)) $this->fail($staff, 'Too many wrong attempts. Please wait 15 minutes and try again.');
        $u = $this->UserModel->by_email($email);
        // always run password_verify (even for unknown emails) so response time does not reveal which emails exist
        $hash = $u['password_hash'] ?? '$2y$10$KWVj55JT.MtZUELFuZGH3OxA3815e58s7wtmIJFWHbtyqiJxN705O';
        $ok = password_verify($pass, $hash) && $u && (int) $u['is_active'] === 1;
        if ($ok && $staff && !in_array($u['role'], ['staff', 'admin'], true)) $ok = false;
        if ($ok && !$staff && $u['role'] !== 'guest') $ok = false;      // staff must use the staff sign-in page
        $this->UserModel->record_attempt($email, $ok);
        if (!$ok) $this->fail($staff, 'Email or password is incorrect.');

        if ($u['role'] === 'guest' && empty($u['mobile_verified_at'])) {
            $_SESSION['pending_uid'] = (int) $u['id'];
            $this->send_code($u, 'verify');
            go('verify');
        }
        login_user((int) $u['id']);
        audit('login', $u['role']);
        go(in_array($u['role'], ['staff', 'admin'], true) ? 'admin' : 'my-bookings');
    }

    private function fail(bool $staff, string $msg): void
    {
        flash('bad', $msg);
        go($staff ? 'admin/login' : 'login');
    }

    public function logout()
    {
        if (current_user()) audit('logout');
        logout_user();
        session_start();               // fresh session just to show the message
        flash('ok', 'You have been signed out.');
        go('login');
    }

    /* ---------------- register + verify mobile ---------------- */
    public function register_form() { render('auth/register', ['old' => [], 'title' => 'Create account']); }

    public function register()
    {
        $old = ['full_name' => post_str('full_name'), 'email' => strtolower(post_str('email')), 'mobile' => post_str('mobile')];
        $regKey = 'register:' . client_ip();                      // max 5 new accounts per IP every 15 minutes
        if ($this->UserModel->too_many_attempts($regKey)) { flash('bad', 'Too many sign-ups from this network. Please try again in 15 minutes.'); render('auth/register', ['old' => $old, 'title' => 'Create account']); return; }
        $pass = post_str('password'); $mobile = normalize_mobile($old['mobile']); $err = [];
        if (!len_ok($old['full_name'], 2, 120)) $err[] = 'Please enter your full name.';
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || !len_ok($old['email'], 5, 150)) $err[] = 'Please enter a valid email address.';
        if (!$mobile) $err[] = 'Please enter a valid Philippine mobile number (09XX XXX XXXX).';
        if (!$this->UserModel->password_ok($pass)) $err[] = 'Password must be 8–72 characters with at least one letter and one number.';
        if ($pass !== post_str('password2')) $err[] = 'The two passwords do not match.';
        if (post_str('agree') !== '1') $err[] = 'Please accept the house rules and privacy notice.';
        if (!$err && ($this->UserModel->by_email($old['email']) || $this->UserModel->by_mobile($mobile))) $err[] = 'An account with that email or mobile number already exists.';
        if ($err) { foreach ($err as $m) flash('bad', $m); render('auth/register', ['old' => $old, 'title' => 'Create account']); return; }

        $this->UserModel->record_attempt($regKey, false);
        $id = $this->UserModel->create($old['full_name'], $old['email'], $mobile, $pass);
        $_SESSION['pending_uid'] = $id;
        $this->send_code($this->UserModel->by_id($id), 'verify');
        go('verify');
    }

    private function send_code(array $u, string $purpose): void
    {
        $code = $this->UserModel->issue_code((int) $u['id'], $purpose);
        $this->NotificationModel->code((int) $u['id'], $u['mobile'], $purpose, $code);
        if (!is_production() || env_get('DEMO_SHOW_CODES') === '1') flash('info', 'DEMO MODE – your code is ' . $code . ' (this message is hidden on a real site).');
    }

    public function verify_form()
    {
        if (empty($_SESSION['pending_uid'])) go('login');
        render('auth/verify', ['title' => 'Verify your number']);
    }
    public function verify()
    {
        $id = (int) ($_SESSION['pending_uid'] ?? 0); $u = $id ? $this->UserModel->by_id($id) : null;
        if (!$u) go('login');
        if ($this->UserModel->check_code($id, 'verify', preg_replace('/\D/', '', post_str('code')))) {
            q('UPDATE users SET mobile_verified_at = ? WHERE id = ?', [now_str(), $id]);
            unset($_SESSION['pending_uid']);
            login_user($id);
            flash('ok', 'Your number is verified. Welcome to HOTELGO!');
            go('my-bookings');
        }
        flash('bad', 'That code is wrong or has expired.');
        go('verify');
    }
    public function verify_resend()
    {
        $u = !empty($_SESSION['pending_uid']) ? $this->UserModel->by_id((int) $_SESSION['pending_uid']) : null;
        if (!$u) go('login');
        if (!$this->UserModel->can_issue_code((int) $u['id'], 'verify')) { flash('warn', 'Please wait a moment before asking for another code.'); go('verify'); }
        $this->send_code($u, 'verify'); flash('ok', 'A new code was sent.');
        go('verify');
    }

    /* ---------------- forgot / reset password ---------------- */
    public function forgot_form() { render('auth/forgot', ['title' => 'Forgot password']); }
    public function forgot()
    {
        $email = strtolower(post_str('email'));
        $u = $this->UserModel->by_email($email);
        if ($u && (int) $u['is_active'] === 1 && !$this->UserModel->too_many_attempts($email) && $this->UserModel->can_issue_code((int) $u['id'], 'reset')) {
            $this->UserModel->record_attempt($email, false);        // counts toward the limit so nobody can spam codes
            $this->send_code($u, 'reset');
        }
        $_SESSION['reset_email'] = $email;
        flash('ok', 'If that account exists, we sent a reset code to its mobile number.');   // same message either way
        go('reset');
    }
    public function reset_form() { render('auth/reset', ['title' => 'Reset password']); }
    public function reset()
    {
        $email = $_SESSION['reset_email'] ?? ''; $u = $email ? $this->UserModel->by_email($email) : null;
        $pass = post_str('password');
        if (!$u || !$this->UserModel->password_ok($pass) || $pass !== post_str('password2')) { flash('bad', 'Please check your code and use a strong password (8+ characters, letters and numbers).'); go('reset'); }
        if (!$this->UserModel->check_code((int) $u['id'], 'reset', preg_replace('/\D/', '', post_str('code')))) { flash('bad', 'That code is wrong or has expired.'); go('reset'); }
        $this->UserModel->set_password((int) $u['id'], $pass);
        unset($_SESSION['reset_email']);
        flash('ok', 'Password changed. You can sign in now.');
        go($u['role'] === 'guest' ? 'login' : 'admin/login');
    }

    /* ---------------- change password (also forced for the first admin login) ---------------- */
    public function password_form() { render('auth/password', ['title' => 'Change password'], has_role('staff', 'admin') ? 'admin' : 'site'); }
    public function password()
    {
        $u = $this->UserModel->by_id((int) current_user()['id']);
        $new = post_str('password');
        if (!password_verify(post_str('current'), $u['password_hash'])) { flash('bad', 'Your current password is wrong.'); go('account/password'); }
        if (!$this->UserModel->password_ok($new) || $new !== post_str('password2')) { flash('bad', 'New password must be 8–72 characters with letters and numbers, typed twice the same.'); go('account/password'); }
        $this->UserModel->set_password((int) $u['id'], $new);
        audit('password_changed');
        flash('ok', 'Password updated.');
        go(in_array($u['role'], ['staff', 'admin'], true) ? 'admin' : 'my-bookings');
    }
}
