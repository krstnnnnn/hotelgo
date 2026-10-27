<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Real photos of hotels, room types and the home page. Table: images (the picture itself, as base64 text).
| A hotel / room type points to its picture with image_id. A home-page picture is the setting photo_<slot>.
| Nothing is trusted from the browser: the bytes are decoded and checked here before they are saved.
*/
class PhotoModel extends Model
{
    protected $table = 'images';

    /* The home-page picture slots: slot => label shown in the admin */
    const SLOTS = [
        'hero' => 'Top of the home page (large, wide)', 'about' => 'Our story picture', 'quote' => 'Quote banner (wide)', 'cta' => 'Reserve-now banner (wide)',
        'gallery1' => 'Gallery 1', 'gallery2' => 'Gallery 2', 'gallery3' => 'Gallery 3', 'gallery4' => 'Gallery 4', 'gallery5' => 'Gallery 5', 'gallery6' => 'Gallery 6',
    ];
    const MAX_BYTES = 750000;                         // the API accepts at most 1 MB of JSON, base64 adds a third

    /* Checks a "data:image/jpeg;base64,..." string and saves it. Returns [id, ''] or [0, reason]. */
    public function store(string $dataUrl): array
    {
        if (!preg_match('~^data:(image/(?:jpeg|png|webp));base64,([A-Za-z0-9+/=\s]+)$~', $dataUrl, $m)) {
            return [0, 'Choose a JPG, PNG or WebP picture.'];
        }
        $raw = base64_decode(preg_replace('/\s+/', '', $m[2]), true);
        if ($raw === false || $raw === '') return [0, 'The picture could not be read.'];
        if (strlen($raw) > self::MAX_BYTES) return [0, 'The picture is too large (the limit is about 700 KB after resizing).'];
        $info = @getimagesizefromstring($raw);
        $types = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_WEBP => 'image/webp'];
        if (!$info || !isset($types[$info[2]]) || $types[$info[2]] !== $m[1]) return [0, 'That file is not a real JPG, PNG or WebP picture.'];
        if ($info[0] < 200 || $info[1] < 150) return [0, 'The picture is too small (at least 200 x 150 pixels).'];
        if ($info[0] > 6000 || $info[1] > 6000) return [0, 'The picture is too big (at most 6000 pixels on a side).'];
        $id = (int) $this->db->table($this->table)->insert(['mime' => $m[1], 'bytes' => strlen($raw), 'data' => base64_encode($raw), 'created_at' => now_str()]);
        return $id > 0 ? [$id, ''] : [0, 'The picture could not be saved.'];
    }

    /* For the public picture address: [mime, bytes] or null */
    public function file(int $id): ?array
    {
        $r = $this->db->table($this->table)->select('mime, data')->where('id', $id)->get();
        if (!$r) return null;
        $raw = base64_decode((string) $r['data'], true);
        return $raw === false ? null : [$r['mime'], $raw];
    }

    public function drop(int $id): void
    {
        if ($id > 0) $this->db->table($this->table)->where('id', $id)->delete();
    }

    /* Home-page pictures: slot => image id (only the slots that have a picture) */
    public function site_slots(): array
    {
        $out = [];
        foreach ($this->db->table('settings')->get_all() as $r) {
            if (strncmp($r['skey'], 'photo_', 6) === 0 && isset(self::SLOTS[substr($r['skey'], 6)]) && (int) $r['svalue'] > 0) {
                $out[substr($r['skey'], 6)] = (int) $r['svalue'];
            }
        }
        return $out;
    }

    /* Points an owner (hotel / room type / home-page slot) to an image, or clears it ($imageId = 0). Returns the old image id. */
    public function attach(string $kind, int $ownerId, string $slot, int $imageId): int
    {
        if ($kind === 'site') {
            $key = 'photo_' . $slot;
            $row = $this->db->table('settings')->where('skey', $key)->get();
            $old = $row ? (int) $row['svalue'] : 0;
            if ($row) { $this->db->table('settings')->where('skey', $key)->update(['svalue' => (string) $imageId]); }
            else { $this->db->table('settings')->insert(['skey' => $key, 'svalue' => (string) $imageId]); }
            return $old;
        }
        $table = $kind === 'hotel' ? 'hotels' : 'room_types';
        $row = $this->db->table($table)->select('image_id')->where('id', $ownerId)->get();
        $old = $row ? (int) $row['image_id'] : 0;
        $this->db->table($table)->where('id', $ownerId)->update(['image_id' => $imageId > 0 ? $imageId : null]);
        return $old;
    }
}
