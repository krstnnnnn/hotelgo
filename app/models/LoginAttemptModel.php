<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Sign-in protection. Table: login_attempts.
| Too many wrong tries for one account or from one network lock sign-in for a while.
| Limits come from app/config/hotelgo.php (hotelgo_lock_*).
*/
class LoginAttemptModel extends Model
{
    protected $table = 'login_attempts';

    public function record(string $key, bool $ok): void
    {
        $key = strtolower(mb_substr($key, 0, 150));
        $this->insert(['email' => $key, 'ip' => client_ip(), 'success' => $ok ? 1 : 0, 'created_at' => now_str()]);
        if ($ok) {   // a good sign-in wipes the old wrong tries
            $this->db->table($this->table)->where('email', $key)->delete();
        }
    }

    public function too_many(string $key): bool
    {
        $since   = date('Y-m-d H:i:s', time() - (int) config_item('hotelgo_lock_window'));
        $byKey   = $this->db->table($this->table)->where('email', strtolower($key))->where('success', 0)->where('created_at', '>', $since)->count();
        $byIp    = $this->db->table($this->table)->where('ip', client_ip())->where('success', 0)->where('created_at', '>', $since)->count();
        return $byKey >= (int) config_item('hotelgo_lock_per_account') || $byIp >= (int) config_item('hotelgo_lock_per_ip');
    }
}
