<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Guests, front-desk staff and the owner. Table: users.
| Create / read / update / delete for all three kinds of account.
| (Sign-in tries live in LoginAttemptModel, one-time codes in OtpModel.)
*/
class UserModel extends Model
{
    protected $table = 'users';

    const SESSION_COLUMNS = 'u.id, u.full_name, u.email, u.mobile, u.role, u.is_active, u.must_change_password, u.mobile_verified_at, u.hotel_id, h.name AS hotel_name';

    /* ---------------- read ---------------- */
    public function by_email(string $email): ?array
    {
        return $this->find_by('email', strtolower($email)) ?: null;
    }
    public function by_mobile(string $mobile): ?array
    {
        return $this->find_by('mobile', $mobile) ?: null;
    }
    public function by_id(int $id): ?array
    {
        return $this->find($id) ?: null;
    }
    /* The signed-in person, re-read from the database on every request (an admin can disable an account at any time) */
    public function session_user(int $id): ?array
    {
        return $this->db->table('users u')->select(self::SESSION_COLUMNS)->left_join('hotels h', 'h.id = u.hotel_id')
            ->where('u.id', $id)->where('u.is_active', 1)->get() ?: null;
    }
    public function touch_login(int $id): void
    {
        $this->db->table($this->table)->where('id', $id)->update(['last_login_at' => now_str()]);
    }

    /* Guests with their number of stays and last visit */
    /* Hotel staff only see guests who booked at their own hotel. Returns null when there is no limit (platform admin). */
    private function my_guest_ids(): ?array
    {
        $h = \Hotelgo\Support\Scope::hotel();
        if ($h === null) return null;
        return array_map('intval', array_column($this->db->table('bookings')->select('user_id')->where('hotel_id', $h)->where('user_id', '>', 0)->group_by('user_id')->get_all(), 'user_id'));
    }
    public function guests(string $search = '', int $limit = 100): array
    {
        $mine = $this->my_guest_ids();
        if ($mine !== null && !$mine) return [];
        $this->db->table($this->table)
            ->select('id, full_name, email, mobile, created_at')
            ->where('role', 'guest');
        if ($mine !== null) {
            $this->db->in('id', $mine);
        }
        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $mail = strtolower($search);
            $this->db->grouped(function ($db) use ($like, $mail) {
                $db->like('full_name', $like)->or_where('email', $mail);
            });
        }
        $rows = $this->db->order_by('created_at', 'DESC')->limit($limit)->get_all();
        if (!$rows) {
            return [];
        }
        $stats = [];
        $agg = $this->db->table('bookings')
            ->select('user_id, COUNT(*) AS stays, MAX(start_at) AS last_visit')
            ->in('user_id', array_column($rows, 'id'))->where('status', '<>', 'expired')
            ->group_by('user_id')->get_all();
        foreach ($agg as $a) {
            $stats[(int) $a['user_id']] = $a;
        }
        foreach ($rows as &$r) {
            $r['stays']      = (int) ($stats[(int) $r['id']]['stays'] ?? 0);
            $r['last_visit'] = $stats[(int) $r['id']]['last_visit'] ?? null;
        }
        return $rows;
    }
    public function guest(int $id): ?array
    {
        $mine = $this->my_guest_ids();
        if ($mine !== null && !in_array($id, $mine, true)) return null;
        return $this->db->table($this->table)
            ->select('id, full_name, email, mobile, created_at, staff_note')
            ->where('id', $id)->where('role', 'guest')->get() ?: null;
    }

    /* Staff and owner accounts */
    public function staff_list(): array
    {
        $this->db->table('users u')
            ->select('u.id, u.full_name, u.email, u.mobile, u.role, u.is_active, u.last_login_at, u.hotel_id, h.name AS hotel_name')
            ->left_join('hotels h', 'h.id = u.hotel_id')
            ->in('u.role', ['staff', 'admin']);
        \Hotelgo\Support\Scope::limit($this->db, 'u.hotel_id');
        $rows = $this->db->order_by('u.full_name', 'ASC')->get_all();
        usort($rows, fn($a, $b) => strcmp($a['role'], $b['role']));   // admin first, then staff (names stay in order)
        return $rows;
    }

    /* ---------------- create ---------------- */
    public function create(string $name, string $email, string $mobile, string $password, string $role = 'guest', bool $mustChange = false, ?int $hotelId = null): int
    {
        return (int) $this->insert([
            'full_name'            => $name,
            'email'                => strtolower($email),
            'mobile'               => $mobile,
            'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
            'role'                 => $role,
            'is_active'            => 1,
            'must_change_password' => $mustChange ? 1 : 0,
            'hotel_id'             => $hotelId,
            'created_at'           => now_str(),
        ]);
    }

    /* The owner creates a front-desk account with a temporary password. Returns [ok, message, temporary password, id]. */
    public function create_staff(string $name, string $email, string $mobile, int $hotelId = 0, string $role = 'staff'): array
    {
        $role = ($role === 'admin' && \Hotelgo\Support\Scope::platform()) ? 'admin' : 'staff';      // only the platform admin can appoint a hotel owner
        $hotelId = \Hotelgo\Support\Scope::hotel() ?: $hotelId;                 // a hotel owner can only add staff to their own hotel
        if ($hotelId < 1 || $this->db->table('hotels')->where('id', $hotelId)->count() < 1) {
            return [false, 'Choose the hotel this person works for.', '', 0];
        }
        $email  = strtolower(trim($email));
        $mobile = normalize_mobile($mobile);
        if (!len_ok($name, 2, 120) || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$mobile || $this->by_email($email) || $this->by_mobile($mobile)) {
            return [false, 'Please check the name, email and mobile number (they must be new).', '', 0];
        }
        $temp = bin2hex(random_bytes(5)) . 'A1';
        $id   = $this->create($name, $email, $mobile, $temp, $role, true, $hotelId);
        $this->mark_verified($id);
        return [true, 'Staff account created.', $temp, $id];
    }

    /* ---------------- update ---------------- */    /* ---------------- update ---------------- */
    public function set_password(int $id, string $password): void
    {
        $this->update($id, ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'must_change_password' => 0]);
    }
    public function mark_verified(int $id): void
    {
        $this->update($id, ['mobile_verified_at' => now_str()]);
    }
    public function save_note(int $id, string $note): void
    {
        $this->db->table($this->table)->where('id', $id)->where('role', 'guest')->update(['staff_note' => mb_substr($note, 0, 500)]);
    }
    /* Name, email, mobile (the caller checks that email and mobile are not taken) */
    public function save_profile(int $id, array $data): void
    {
        $allowed = array_intersect_key($data, array_flip(['full_name', 'email', 'mobile']));
        if (isset($allowed['email'])) {
            $allowed['email'] = strtolower($allowed['email']);
        }
        if ($allowed) {
            $this->update($id, $allowed);
        }
    }
    /* Enable / disable a staff account. Returns the new state, or null when it is not a staff account. */
    public function toggle_staff(int $id): ?int
    {
        $u = $this->db->table($this->table)->select('id, is_active')->where('id', $id)->in('role', ['staff', 'admin'])->where('hotel_id', '>', 0)->get();
        if (!$u) {
            return null;
        }
        $new = (int) $u['is_active'] === 1 ? 0 : 1;
        $this->update($id, ['is_active' => $new]);
        return $new;
    }

    /* ---------------- delete ---------------- */
    /* A person with bookings is never deleted (their history must stay). Returns [ok, reason]. */
    public function remove(int $id): array
    {
        $u = $this->by_id($id);
        if (!$u) {
            return [false, 'Account not found.'];
        }
        if ($u['role'] === 'admin' && empty($u['hotel_id'])) {
            return [false, 'The platform admin account cannot be deleted.'];
        }
        if ($this->db->table('bookings')->where('user_id', $id)->count() > 0) {
            return [false, 'This person has bookings. Disable the account instead of deleting it.'];
        }
        $this->db->table('otp_codes')->where('user_id', $id)->delete();
        $this->db->table('refresh_tokens')->where('user_id', $id)->delete();
        $this->delete($id);
        return [true, 'Account deleted.'];
    }

    /* ---------------- rules ---------------- */
    public function password_ok(string $password): bool
    {
        return strlen($password) >= 8 && strlen($password) <= 72 && preg_match('/[A-Za-z]/', $password) && preg_match('/\d/', $password);
    }
}
