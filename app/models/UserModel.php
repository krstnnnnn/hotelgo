<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class UserModel extends Model
{
    protected $table = 'users';
    protected $primary_key = 'id';

    public function by_email(string $email): ?array { return q_one('SELECT * FROM users WHERE email = ?', [strtolower($email)]); }
    public function by_mobile(string $mobile): ?array { return q_one('SELECT * FROM users WHERE mobile = ?', [$mobile]); }
    public function by_id(int $id): ?array { return q_one('SELECT * FROM users WHERE id = ?', [$id]); }

    public function create(string $name, string $email, string $mobile, string $password, string $role = 'guest', bool $mustChange = false): int
    {
        return insert_row('users', [
            'full_name' => $name, 'email' => strtolower($email), 'mobile' => $mobile,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role, 'is_active' => 1, 'must_change_password' => $mustChange ? 1 : 0, 'created_at' => now_str(),
        ]);
    }
    public function set_password(int $id, string $password): void
    {
        q('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
    }
    public function password_ok(string $password): bool
    {
        return strlen($password) >= 8 && strlen($password) <= 72 && preg_match('/[A-Za-z]/', $password) && preg_match('/\d/', $password);
    }

    /* ---- login throttling: 5 wrong tries per account / 20 per IP in 15 minutes ---- */
    public function too_many_attempts(string $email): bool
    {
        $since = date('Y-m-d H:i:s', time() - 900);
        $byEmail = (int) q_val('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND success = 0 AND created_at > ?', [strtolower($email), $since]);
        $byIp    = (int) q_val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND created_at > ?', [client_ip(), $since]);
        return $byEmail >= 5 || $byIp >= 20;
    }
    public function record_attempt(string $email, bool $ok): void
    {
        insert_row('login_attempts', ['email' => strtolower(mb_substr($email, 0, 150)), 'ip' => client_ip(), 'success' => $ok ? 1 : 0, 'created_at' => now_str()]);
        if ($ok) q('DELETE FROM login_attempts WHERE email = ?', [strtolower($email)]);
    }

    /* ---- one-time codes (mobile verification and password reset) ---- */
    public function issue_code(int $userId, string $purpose): string
    {
        q('UPDATE otp_codes SET used_at = ? WHERE user_id = ? AND purpose = ? AND used_at IS NULL', [now_str(), $userId, $purpose]);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        insert_row('otp_codes', ['user_id' => $userId, 'purpose' => $purpose, 'code_hash' => password_hash($code, PASSWORD_DEFAULT), 'expires_at' => date('Y-m-d H:i:s', time() + 600), 'attempts' => 0, 'created_at' => now_str()]);
        return $code;
    }
    /* at most 1 new code every 30 seconds and 5 codes per 15 minutes (stops SMS spam) */
    public function can_issue_code(int $userId, string $purpose): bool
    {
        $last = q_val('SELECT MAX(created_at) FROM otp_codes WHERE user_id = ? AND purpose = ?', [$userId, $purpose]);
        if ($last && strtotime($last) > time() - 30) return false;
        return (int) q_val('SELECT COUNT(*) FROM otp_codes WHERE user_id = ? AND purpose = ? AND created_at > ?', [$userId, $purpose, date('Y-m-d H:i:s', time() - 900)]) < 5;
    }
    public function check_code(int $userId, string $purpose, string $code): bool
    {
        $row = q_one('SELECT * FROM otp_codes WHERE user_id = ? AND purpose = ? AND used_at IS NULL AND expires_at > ? ORDER BY id DESC LIMIT 1', [$userId, $purpose, now_str()]);
        if (!$row || (int) $row['attempts'] >= 5) return false;
        q('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
        if (!password_verify($code, $row['code_hash'])) return false;
        q('UPDATE otp_codes SET used_at = ? WHERE id = ?', [now_str(), $row['id']]);
        return true;
    }
}
