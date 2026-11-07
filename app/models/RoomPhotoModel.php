<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| The picture gallery of a room type. Table: room_photos.
|   The main picture of a room type (room_types.image_id) always comes first.
*/
class RoomPhotoModel extends Model
{
    protected $table = 'room_photos';

    /* the picture ids of a room type: main picture first, then the gallery in order (no duplicates) */
    public function images_of(int $typeId, int $mainId = 0): array
    {
        $rows = $this->db->table($this->table)->select('image_id')->where('room_type_id', $typeId)->order_by('sort_order', 'ASC')->order_by('id', 'ASC')->get_all();
        $ids = $mainId ? [$mainId] : [];
        foreach ($rows as $r) $ids[] = (int) $r['image_id'];
        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    /* adds a picture to the gallery (a picture is only listed once) */
    public function add(int $typeId, int $imageId): void
    {
        if ($this->db->table($this->table)->where('room_type_id', $typeId)->where('image_id', $imageId)->get()) return;
        $next = (int) $this->db->raw('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM room_photos WHERE room_type_id = ?', [$typeId])->fetchColumn();
        $this->db->table($this->table)->insert(['room_type_id' => $typeId, 'image_id' => $imageId, 'sort_order' => $next, 'created_at' => now_str()]);
    }

    /* takes a picture out of the gallery (the picture itself is kept) */
    public function remove(int $typeId, int $imageId): void
    {
        $this->db->table($this->table)->where('room_type_id', $typeId)->where('image_id', $imageId)->delete();
    }
}
