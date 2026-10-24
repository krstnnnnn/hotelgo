<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Room types and their rates (Standard Queen, Family Suite ...). Table: room_types.
| Create / read / update / delete.
*/
class RoomTypeModel extends Model
{
    protected $table = 'room_types';

    const COLUMNS = ['name', 'description', 'amenities', 'max_guests', 'price_3h', 'price_6h', 'price_12h', 'price_overnight', 'cleaning_buffer_min', 'is_active'];

    /* ---------------- read ---------------- */
    public function types(bool $onlyActive = true): array
    {
        $this->db->table($this->table);
        if ($onlyActive) {
            $this->db->where('is_active', 1);
        }
        return $this->db->order_by('price_3h', 'ASC')->get_all();
    }
    public function type(int $id): ?array
    {
        return $this->find($id) ?: null;
    }

    /* Checks and cleans form / API input. Returns the cleaned columns, or null when something is wrong. */
    public function clean(array $in): ?array
    {
        $d = [
            'name'                => trim((string) ($in['name'] ?? '')),
            'description'         => mb_substr(trim((string) ($in['description'] ?? '')), 0, 500),
            'amenities'           => mb_substr(trim((string) ($in['amenities'] ?? '')), 0, 250),
            'max_guests'          => (int) ($in['max_guests'] ?? 2),
            'cleaning_buffer_min' => (int) ($in['cleaning_buffer_min'] ?? 15),
            'is_active'           => in_array($in['is_active'] ?? 0, [1, '1', true, 'on'], true) ? 1 : 0,
        ];
        foreach (['price_3h', 'price_6h', 'price_12h', 'price_overnight'] as $k) {
            $d[$k] = filter_var((string) ($in[$k] ?? ''), FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0, 'max_range' => 100000]]);
        }
        $bad = !len_ok($d['name'], 2, 80) || $d['max_guests'] < 1 || $d['max_guests'] > 20 || $d['cleaning_buffer_min'] < 0 || $d['cleaning_buffer_min'] > 180
            || in_array(false, [$d['price_3h'], $d['price_6h'], $d['price_12h'], $d['price_overnight']], true);
        return $bad ? null : $d;
    }

    /* ---------------- create / update ---------------- */
    /* $data holds the columns in self::COLUMNS. Returns the id. */
    public function save(?int $id, array $data): int
    {
        $row = array_intersect_key($data, array_flip(self::COLUMNS));
        if ($id) {
            $this->update($id, $row);
            return $id;
        }
        return (int) $this->insert($row);
    }

    /* ---------------- delete ---------------- */
    /* A type that has rooms or bookings is kept (hide it instead). Returns [ok, reason]. */
    public function remove(int $id): array
    {
        if (!$this->type($id)) {
            return [false, 'Room type not found.'];
        }
        if ($this->db->table('rooms')->where('room_type_id', $id)->count() > 0) {
            return [false, 'This type still has rooms. Delete or move the rooms first, or untick "Visible to guests".'];
        }
        if ($this->db->table('bookings')->where('room_type_id', $id)->count() > 0) {
            return [false, 'This type has bookings in the history. Untick "Visible to guests" instead of deleting it.'];
        }
        $this->delete($id);
        return [true, 'Room type deleted.'];
    }
}
