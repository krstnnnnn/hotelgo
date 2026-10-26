<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| The physical rooms (101, 102 ...) and the live room board. Table: rooms.
| Create / read / update / delete. Prices and room types live in RoomTypeModel.
*/
class RoomModel extends Model
{
    protected $table = 'rooms';

    const STATUSES = ['active', 'maintenance'];

    /* ---------------- read ---------------- */
    public function rooms(): array
    {
        $this->db->table('rooms r')
            ->select('r.id, r.room_no, r.room_type_id, r.floor, r.status, r.hotel_id, t.name AS type_name, h.name AS hotel_name')
            ->join('room_types t', 't.id = r.room_type_id')
            ->left_join('hotels h', 'h.id = r.hotel_id');
        \Hotelgo\Support\Scope::limit($this->db, 'r.hotel_id');
        return $this->db->order_by('h.name', 'ASC')->order_by('r.room_no', 'ASC')->get_all();
    }
    public function room(int $id): ?array
    {
        $this->db->table('rooms r')
            ->select('r.id, r.room_no, r.room_type_id, r.floor, r.status, r.hotel_id, t.name AS type_name, h.name AS hotel_name')
            ->join('room_types t', 't.id = r.room_type_id')
            ->left_join('hotels h', 'h.id = r.hotel_id')
            ->where('r.id', $id);
        \Hotelgo\Support\Scope::limit($this->db, 'r.hotel_id');
        return $this->db->get() ?: null;
    }
    /* Rooms of one type that can be sold (not under maintenance) */
    public function active_in_type(int $typeId): array
    {
        return $this->db->table($this->table)->select('id, room_no')
            ->where('room_type_id', $typeId)->where('status', 'active')
            ->order_by('room_no', 'ASC')->get_all();
    }
    /* Room numbers only have to be unique inside one hotel */
    public function room_no_taken(string $roomNo, int $hotelId, int $exceptId = 0): bool
    {
        $this->db->table($this->table)->where('room_no', $roomNo)->where('hotel_id', $hotelId);
        if ($exceptId > 0) {
            $this->db->where('id', '<>', $exceptId);
        }
        return $this->db->count() > 0;
    }

    /* Live board: available / occupied / reserved / cleaning / maintenance (3 queries for all rooms) */
    public function board(): array
    {
        $now   = now_str();
        $soon  = date('Y-m-d H:i:s', time() + 7200);
        $ago   = date('Y-m-d H:i:s', time() - 900);

        $occupied = $reserved = $cleaning = [];
        $scoped = fn($q) => \Hotelgo\Support\Scope::limit($q, 'hotel_id');
        foreach ($scoped($this->db->table('bookings')->select('room_id, end_at')->where('status', 'checked_in'))->order_by('end_at', 'DESC')->get_all() as $b) {
            $occupied[(int) $b['room_id']] ??= $b;
        }
        foreach ($scoped($this->db->table('bookings')->select('room_id, start_at')->where('status', 'confirmed')->where('end_at', '>', $now)->where('start_at', '<=', $soon))->order_by('start_at', 'ASC')->get_all() as $b) {
            $reserved[(int) $b['room_id']] ??= $b;
        }
        foreach ($scoped($this->db->table('bookings')->select('room_id')->where('status', 'checked_out')->where('checked_out_at', '>', $ago))->get_all() as $b) {
            $cleaning[(int) $b['room_id']] = true;
        }

        $out = [];
        foreach ($this->rooms() as $r) {
            $id = (int) $r['id'];
            if ($r['status'] !== 'active') {
                [$state, $note] = ['maintenance', 'Under maintenance'];
            } elseif (isset($occupied[$id])) {
                [$state, $note] = ['occupied', 'until ' . fmt_dt($occupied[$id]['end_at'], 'g:i A')];
            } elseif (isset($reserved[$id])) {
                [$state, $note] = ['reserved', 'arrives ' . fmt_dt($reserved[$id]['start_at'], 'g:i A')];
            } elseif (isset($cleaning[$id])) {
                [$state, $note] = ['cleaning', 'Cleaning'];
            } else {
                [$state, $note] = ['available', 'Ready'];
            }
            $out[] = $r + ['state' => $state, 'note' => $note];
        }
        return $out;
    }


    /* Numbers for the dashboard from a board: how many rooms in each state and the occupancy percentage */
    public function summary(array $board): array
    {
        $count = array_count_values(array_column($board, 'state'));
        $total = count($board) ?: 1;
        return ['count' => $count, 'total' => $total, 'occupancy' => (int) round((($count['occupied'] ?? 0) + ($count['reserved'] ?? 0)) / $total * 100)];
    }

    /* Checks form / API input for a room. Returns [cleaned columns, ''] or [null, reason]. */
    public function clean_room(array $in, int $exceptId = 0): array
    {
        $no     = trim((string) ($in['room_no'] ?? ''));
        $typeId = (int) ($in['room_type_id'] ?? 0);
        $status = (string) ($in['status'] ?? 'active');
        $type   = $this->call->model('RoomTypeModel')->type($typeId);      // only a type of your own hotel passes
        if (!$type) {
            return [null, 'Choose a room type.'];
        }
        if (!preg_match('/^[A-Za-z0-9\-]{1,10}$/', $no) || $this->room_no_taken($no, (int) $type['hotel_id'], $exceptId)) {
            return [null, 'Room number is invalid or already exists in this hotel.'];
        }
        if (!in_array($status, self::STATUSES, true)) {
            return [null, 'Invalid status.'];
        }
        return [['hotel_id' => (int) $type['hotel_id'], 'room_no' => $no, 'room_type_id' => $typeId, 'floor' => max(1, min(50, (int) ($in['floor'] ?? 1))), 'status' => $status], ''];
    }

    /* ---------------- create / update ---------------- */
    public function add_room(string $roomNo, int $typeId, int $floor): int
    {
        $type = $this->call->model('RoomTypeModel')->type($typeId);
        return (int) $this->insert(['hotel_id' => $type['hotel_id'] ?? null, 'room_no' => $roomNo, 'room_type_id' => $typeId, 'floor' => $floor, 'status' => 'active']);
    }
    /* $data may hold room_no, room_type_id, floor, status */
    public function save_room(int $id, array $data): void
    {
        $row = array_intersect_key($data, array_flip(['hotel_id', 'room_no', 'room_type_id', 'floor', 'status']));
        if ($row) {
            $this->update($id, $row);
        }
    }
    public function set_status(int $id, string $status): void
    {
        $this->update($id, ['status' => $status]);
    }

    /* ---------------- delete ---------------- */
    /* A room that has bookings is kept (set it to maintenance instead). Returns [ok, reason]. */
    public function remove(int $id): array
    {
        if (!$this->room($id)) {
            return [false, 'Room not found.'];
        }
        if ($this->db->table('bookings')->where('room_id', $id)->count() > 0) {
            return [false, 'This room has bookings in the history. Set it to maintenance instead of deleting it.'];
        }
        $this->delete($id);
        return [true, 'Room deleted.'];
    }
}
