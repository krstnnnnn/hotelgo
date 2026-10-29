<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| The hotels that sell rooms through HOTELGO (one platform, many hotels). Table: hotels.
| Create / read / update / delete. Room types, rooms, staff and bookings all point to a hotel (hotel_id).
*/
class HotelModel extends Model
{
    protected $table = 'hotels';

    const COLUMNS = ['name', 'slug', 'area', 'address', 'description', 'lat', 'lng', 'phone', 'email', 'is_active', 'calendar_id'];

    /* ---------------- read ---------------- */
    /* Public list: active hotels with their cheapest 3-hour price and number of room types */
    public function listing(string $area = ''): array
    {
        $this->db->table('hotels h')->select('h.id, h.name, h.slug, h.area, h.address, h.description, h.lat, h.lng, h.phone, h.email, h.image_id')->where('h.is_active', 1);
        if ($area !== '') {
            $this->db->where('h.area', $area);
        }
        $rows = $this->db->order_by('h.name', 'ASC')->get_all();
        $stats = [];
        foreach ($this->db->table('room_types')->select('hotel_id, COUNT(*) AS types, MIN(price_3h) AS from_price')->where('is_active', 1)->group_by('hotel_id')->get_all() as $s) {
            $stats[(int) $s['hotel_id']] = $s;
        }
        foreach ($rows as &$r) {
            $r['types']      = (int) ($stats[(int) $r['id']]['types'] ?? 0);
            $r['from_price'] = isset($stats[(int) $r['id']]) ? (float) $stats[(int) $r['id']]['from_price'] : null;
            $r['lat'] = (float) $r['lat']; $r['lng'] = (float) $r['lng']; $r['image_id'] = $r['image_id'] ? (int) $r['image_id'] : null;
        }
        return array_values(array_filter($rows, fn($r) => $r['types'] > 0));        // a hotel with nothing to sell is not listed
    }
    /* Management list: every hotel for the platform admin, only their own for a hotel owner */
    public function all(): array
    {
        $this->db->table($this->table);
        if (\Hotelgo\Support\Scope::hotel() !== null) {
            $this->db->where('id', \Hotelgo\Support\Scope::hotel());
        }
        $rows = $this->db->order_by('name', 'ASC')->get_all();
        $counts = [];
        foreach ($this->db->table('rooms')->select('hotel_id, COUNT(*) AS n')->group_by('hotel_id')->get_all() as $c) { $counts[(int) $c['hotel_id']] = (int) $c['n']; }
        foreach ($rows as &$r) { $r['rooms'] = $counts[(int) $r['id']] ?? 0; $r['lat'] = (float) $r['lat']; $r['lng'] = (float) $r['lng']; $r['image_id'] = $r['image_id'] ? (int) $r['image_id'] : null; }
        return $rows;
    }
    public function hotel(int $id): ?array
    {
        $h = $this->find($id) ?: null;
        return ($h && \Hotelgo\Support\Scope::allows($id)) ? $h : null;
    }
    public function areas(): array
    {
        return array_column($this->db->table($this->table)->select('area')->where('is_active', 1)->where('area', '<>', '')->group_by('area')->order_by('area', 'ASC')->get_all(), 'area');
    }

    /* ---------------- checks ---------------- */
    public static function slugify(string $s): string
    {
        $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
        return $s !== '' ? mb_substr($s, 0, 70) : 'hotel';
    }
    /* Cleans form / API input. Returns [columns, ''] or [null, reason] */
    public function clean(array $in, int $exceptId = 0): array
    {
        $name = trim((string) ($in['name'] ?? ''));
        $lat  = filter_var((string) ($in['lat'] ?? ''), FILTER_VALIDATE_FLOAT);
        $lng  = filter_var((string) ($in['lng'] ?? ''), FILTER_VALIDATE_FLOAT);
        if (!len_ok($name, 2, 120)) return [null, 'Enter the hotel name (2 to 120 letters).'];
        if ($lat === false || $lng === false || $lat < 12.5 || $lat > 14.5 || $lng < 120.0 || $lng > 122.0) return [null, 'The map position must be in the Puerto Galera area (latitude about 13.5, longitude about 120.9).'];
        $email = trim((string) ($in['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) return [null, 'The hotel email is not valid.'];
        $slug = self::slugify((string) ($in['slug'] ?? '') ?: $name);
        $base = $slug; $n = 2;
        while ($this->slug_taken($slug, $exceptId)) { $slug = $base . '-' . $n++; }
        $cal = trim((string) ($in['calendar_id'] ?? ''));
        if ($cal !== '' && !preg_match('/^[A-Za-z0-9._@%+-]{3,190}$/', $cal)) return [null, 'The Google Calendar ID is not valid. Copy it from Google Calendar > Settings > Integrate calendar.'];
        return [[
            'name' => $name, 'slug' => $slug, 'area' => mb_substr(trim((string) ($in['area'] ?? '')), 0, 80),
            'address' => mb_substr(trim((string) ($in['address'] ?? '')), 0, 255), 'description' => mb_substr(trim((string) ($in['description'] ?? '')), 0, 500),
            'lat' => $lat, 'lng' => $lng, 'phone' => mb_substr(trim((string) ($in['phone'] ?? '')), 0, 30), 'email' => $email,
            'is_active' => in_array($in['is_active'] ?? 1, [1, '1', true, 'on'], true) ? 1 : 0,
            'calendar_id' => $cal !== '' ? $cal : null,
        ], ''];
    }
    private function slug_taken(string $slug, int $exceptId): bool
    {
        $this->db->table($this->table)->where('slug', $slug);
        if ($exceptId > 0) $this->db->where('id', '<>', $exceptId);
        return $this->db->count() > 0;
    }

    /* ---------------- create / update ---------------- */
    public function save(?int $id, array $data): int
    {
        $row = array_intersect_key($data, array_flip(self::COLUMNS));
        if ($id) { $this->update($id, $row); return $id; }
        $row['created_at'] = now_str();
        return (int) $this->insert($row);
    }

    /* ---------------- delete ---------------- */
    /* A hotel that still has room types, staff or bookings is kept (hide it instead). Returns [ok, reason]. */
    public function remove(int $id): array
    {
        if (!$this->find($id)) return [false, 'Hotel not found.'];
        foreach (['room_types' => 'room types', 'users' => 'staff accounts', 'bookings' => 'bookings'] as $t => $what) {
            if ($this->db->table($t)->where('hotel_id', $id)->count() > 0) {
                return [false, 'This hotel still has ' . $what . '. Untick "Visible to guests" to hide it instead of deleting it.'];
            }
        }
        $img = (int) ($this->find($id)['image_id'] ?? 0);
        if ($img > 0) $this->db->table('images')->where('id', $img)->delete();
        $this->delete($id);
        return [true, 'Hotel deleted.'];
    }
}
