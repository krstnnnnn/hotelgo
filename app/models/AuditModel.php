<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Who did what, and when. Table: audit_logs.
| Nobody can edit these rows. The owner can read them; only the platform admin can clear OLD entries (purge).
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

    /* Delete entries older than $days days (Delete of CRUD). Returns how many were removed. */
    public function purge(int $days): int
    {
        return (int) $this->db->table($this->table)->where('created_at', '<', date('Y-m-d H:i:s', time() - $days * 86400))->delete();
    }
}
