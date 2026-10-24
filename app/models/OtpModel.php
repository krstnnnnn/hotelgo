<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| One-time 6-digit codes (email verification and password reset). Table: otp_codes.
| Only a hash of the code is stored. Limits come from app/config/hotelgo.php (hotelgo_otp_*).
*/
class OtpModel extends Model
{
    protected $table = 'otp_codes';

    /* Creates a new code (older unused codes of the same purpose stop working) and returns it */
    public function issue(int $userId, string $purpose): string
    {
        $this->db->table($this->table)
            ->where('user_id', $userId)->where('purpose', $purpose)->where_null('used_at')
            ->update(['used_at' => now_str()]);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->insert([
            'user_id'    => $userId,
            'purpose'    => $purpose,
            'code_hash'  => password_hash($code, PASSWORD_DEFAULT),
            'expires_at' => date('Y-m-d H:i:s', time() + (int) config_item('hotelgo_otp_ttl')),
            'attempts'   => 0,
            'created_at' => now_str(),
        ]);
        return $code;
    }

    /* At most 1 new code every 30 seconds and 5 codes per 15 minutes (stops spam) */
    public function can_issue(int $userId, string $purpose): bool
    {
        $last = $this->db->table($this->table)->select_max('created_at', 'last_at')
            ->where('user_id', $userId)->where('purpose', $purpose)->get();
        if (!empty($last['last_at']) && strtotime($last['last_at']) > time() - (int) config_item('hotelgo_otp_resend_gap')) {
            return false;
        }
        $recent = $this->db->table($this->table)
            ->where('user_id', $userId)->where('purpose', $purpose)
            ->where('created_at', '>', date('Y-m-d H:i:s', time() - (int) config_item('hotelgo_otp_window')))
            ->count();
        return $recent < (int) config_item('hotelgo_otp_per_window');
    }

    /* True when the code is right. A code dies after too many wrong guesses. */
    public function check(int $userId, string $purpose, string $code): bool
    {
        $row = $this->db->table($this->table)
            ->where('user_id', $userId)->where('purpose', $purpose)->where_null('used_at')
            ->where('expires_at', '>', now_str())
            ->order_by('id', 'DESC')->limit(1)->get();
        if (!$row || (int) $row['attempts'] >= (int) config_item('hotelgo_otp_max_tries')) {
            return false;
        }
        $this->db->table($this->table)->where('id', $row['id'])->increment('attempts');
        if (!password_verify($code, $row['code_hash'])) {
            return false;
        }
        $this->db->table($this->table)->where('id', $row['id'])->update(['used_at' => now_str()]);
        return true;
    }
}
