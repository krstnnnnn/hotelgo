<?php

/*
| Removes the old SAMPLE data that earlier versions added, so the system holds only real records:
|   - the three sample hotels (Sabang Reef Lodge, Talipanan Garden Inn, La Laguna Beach Lodge) with their room types, rooms and front-desk accounts,
|   - the sample "VIP Luxury Suite" and its rooms VIP1 / VIP2,
|   - the sample front-desk account rico@hotelgo.ph.
| SAFE: anything that was really used is kept. A sample hotel or room type that has bookings is not touched, and a sample account
| that has ever signed in is not touched. Running it twice does nothing. The first owner account (admin@hotelgo.ph) is never removed.
*/
class Remove_sample_data {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->database();
    }

    private function count(string $sql, array $args): int
    {
        return (int) $this->_lava->db->raw($sql, $args)->fetchColumn();
    }

    /* delete a room type and its rooms when no booking ever used it */
    private function drop_type(int $typeId): void
    {
        $db = $this->_lava->db;
        if ($this->count('SELECT COUNT(*) FROM `bookings` WHERE `room_type_id` = ?', [$typeId]) > 0) return;
        $db->raw('DELETE FROM `rooms` WHERE `room_type_id` = ?', [$typeId]);
        $db->raw('DELETE FROM `room_types` WHERE `id` = ?', [$typeId]);
    }

    private function drop_sample_user(string $email): void
    {
        $db = $this->_lava->db;
        $u = $db->raw('SELECT `id` FROM `users` WHERE `email` = ? AND `role` <> \'guest\' AND `last_login_at` IS NULL', [$email])->fetch(PDO::FETCH_ASSOC);
        if (!$u) return;
        $id = (int) $u['id'];
        if ($this->count('SELECT COUNT(*) FROM `bookings` WHERE `user_id` = ?', [$id]) > 0) return;
        $db->raw('DELETE FROM `refresh_tokens` WHERE `user_id` = ?', [$id]);
        $db->raw('DELETE FROM `users` WHERE `id` = ?', [$id]);
    }

    public function up()
    {
        $db = $this->_lava->db;

        foreach (['staff.sabang@hotelgo.ph', 'staff.talipanan@hotelgo.ph', 'staff.laguna@hotelgo.ph', 'rico@hotelgo.ph'] as $email) {
            $this->drop_sample_user($email);
        }

        foreach (['sabang-reef-lodge', 'talipanan-garden-inn', 'la-laguna-beach-lodge'] as $slug) {
            $h = $db->raw('SELECT `id` FROM `hotels` WHERE `slug` = ?', [$slug])->fetch(PDO::FETCH_ASSOC);
            if (!$h) continue;
            $hid = (int) $h['id'];
            if ($this->count('SELECT COUNT(*) FROM `bookings` WHERE `hotel_id` = ?', [$hid]) > 0) continue;       // really used: keep it
            if ($this->count('SELECT COUNT(*) FROM `users` WHERE `hotel_id` = ?', [$hid]) > 0) continue;          // someone real works there: keep it
            foreach ($db->raw('SELECT `id` FROM `room_types` WHERE `hotel_id` = ?', [$hid])->fetchAll(PDO::FETCH_COLUMN) as $tid) {
                $this->drop_type((int) $tid);
            }
            if ($this->count('SELECT COUNT(*) FROM `room_types` WHERE `hotel_id` = ?', [$hid]) === 0) {
                $db->raw('DELETE FROM `hotels` WHERE `id` = ?', [$hid]);
            }
        }

        foreach ($db->raw('SELECT `id` FROM `room_types` WHERE `name` = \'VIP Luxury Suite\' AND `is_vip` = 1')->fetchAll(PDO::FETCH_COLUMN) as $tid) {
            $this->drop_type((int) $tid);
        }
    }

    public function down()
    {
        // removed sample data is not brought back
    }
}
