<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Who did what, and when. Table: audit_logs.
| Nobody can edit or delete these rows from the website: only the owner can read them.
*/
class AuditModel extends Model
{
    protected $table = 'audit_logs';

    public function record(?int $userId, string $action, string $detail = ''): void
    {
        $this->insert([
            'user_id'    => $userId,
            'action'     => mb_substr($action, 0, 60),
            'detail'     => mb_substr($detail, 0, 250),
            'ip'         => client_ip(),
            'created_at' => now_str(),
        ]);
    }

    /* The newest rows with the name of the person who did it */
    public function recent(int $limit = 300): array
    {
        $this->db->table('audit_logs a')
            ->select('a.id, a.created_at, a.action, a.detail, a.ip, u.full_name, u.role, h.name AS hotel_name')
            ->left_join('users u', 'u.id = a.user_id')
            ->left_join('hotels h', 'h.id = u.hotel_id');
        return \Hotelgo\Support\Scope::limit($this->db, 'u.hotel_id')            // a hotel owner only sees their own hotel's staff activity
            ->order_by('a.id', 'DESC')
            ->limit($limit)
            ->get_all();
    }

    /* How many times one person did one thing since a time (used to limit the "Send test" buttons) */
    public function count_since(int $userId, string $action, string $since): int
    {
        return (int) $this->db->table($this->table)
            ->where('user_id', $userId)
            ->where('action', $action)
            ->where('created_at', '>', $since)
            ->count();
    }
}
