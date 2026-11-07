<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Saved hotels (the heart on the room list). Table: favorites. One row per signed-in guest and hotel.
*/
class FavoriteModel extends Model
{
    protected $table = 'favorites';

    /* the hotel ids this guest saved */
    public function hotel_ids(int $userId): array
    {
        return array_map('intval', array_column($this->db->table($this->table)->select('hotel_id')->where('user_id', $userId)->get_all(), 'hotel_id'));
    }

    /* saves the hotel, or removes it if it is already saved. Returns true when it is saved now. */
    public function toggle(int $userId, int $hotelId): bool
    {
        $found = $this->db->table($this->table)->where('user_id', $userId)->where('hotel_id', $hotelId)->get();
        if ($found) {
            $this->db->table($this->table)->where('user_id', $userId)->where('hotel_id', $hotelId)->delete();
            return false;
        }
        $this->db->table($this->table)->insert(['user_id' => $userId, 'hotel_id' => $hotelId, 'created_at' => now_str()]);
        return true;
    }
}
