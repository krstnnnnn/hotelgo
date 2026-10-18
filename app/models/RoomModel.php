<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class RoomModel extends Model
{
    protected $table = 'rooms';
    protected $primary_key = 'id';

    public function types(bool $onlyActive = true): array
    {
        return q_all('SELECT * FROM room_types ' . ($onlyActive ? 'WHERE is_active = 1 ' : '') . 'ORDER BY price_3h');
    }
    public function type(int $id): ?array { return q_one('SELECT * FROM room_types WHERE id = ?', [$id]); }
    public function rooms(): array
    {
        return q_all('SELECT r.*, t.name AS type_name FROM rooms r JOIN room_types t ON t.id = r.room_type_id ORDER BY r.room_no');
    }
    public function save_type(?int $id, array $d): void
    {
        $cols = ['name', 'description', 'max_guests', 'amenities', 'price_3h', 'price_6h', 'price_12h', 'price_overnight', 'cleaning_buffer_min', 'is_active'];
        $vals = []; foreach ($cols as $c) $vals[] = $d[$c];
        if ($id) { q('UPDATE room_types SET ' . implode(' = ?, ', $cols) . ' = ? WHERE id = ?', array_merge($vals, [$id])); }
        else { insert_row('room_types', array_combine($cols, $vals)); }
    }
    public function add_room(string $no, int $typeId, int $floor): void { insert_row('rooms', ['room_no' => $no, 'room_type_id' => $typeId, 'floor' => $floor, 'status' => 'active']); }
    public function set_room_status(int $id, string $status): void { q('UPDATE rooms SET status = ? WHERE id = ?', [$status, $id]); }

    /* Live board: available / occupied / reserved / cleaning / maintenance */
    public function board(): array
    {
        $now = now_str(); $out = [];
        foreach ($this->rooms() as $r) {
            $state = 'available'; $note = 'Ready';
            if ($r['status'] !== 'active') { $state = 'maintenance'; $note = 'Under maintenance'; }
            else {
                $in = q_one("SELECT end_at FROM bookings WHERE room_id = ? AND status = 'checked_in' ORDER BY end_at DESC LIMIT 1", [$r['id']]);
                $res = q_one("SELECT start_at FROM bookings WHERE room_id = ? AND status = 'confirmed' AND end_at > ? AND start_at <= ? ORDER BY start_at LIMIT 1", [$r['id'], $now, date('Y-m-d H:i:s', time() + 7200)]);
                $clean = q_one("SELECT checked_out_at FROM bookings WHERE room_id = ? AND status = 'checked_out' AND checked_out_at > ? LIMIT 1", [$r['id'], date('Y-m-d H:i:s', time() - 900)]);
                if ($in) { $state = 'occupied'; $note = 'until ' . fmt_dt($in['end_at'], 'g:i A'); }
                elseif ($res) { $state = 'reserved'; $note = 'arrives ' . fmt_dt($res['start_at'], 'g:i A'); }
                elseif ($clean) { $state = 'cleaning'; $note = 'Cleaning'; }
            }
            $out[] = $r + ['state' => $state, 'note' => $note];
        }
        return $out;
    }
}
