<?php

/*
| Sample hotels in other parts of Puerto Galera so the platform shows MORE THAN ONE hotel.
| Only added while the system still has a single hotel. Edit or hide them in the Hotels page.
| Each demo hotel gets one front-desk account (temporary password ChangeMe!2026, changed at first sign-in).
*/
class Seed_demo_hotels {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->database();
    }

    public function up()
    {
        $db = $this->_lava->db;
        if ($db->table('hotels')->count() >= 2) {
            return;
        }
        $hash = '$2y$10$vxyXlANjBTeIOTm7ij4wVO8GHECwFLqgg5tVKkhpt800.Rn8TH8Gi';
        $now  = date('Y-m-d H:i:s');
        $hotels = [
            ['Sabang Reef Lodge', 'sabang-reef-lodge', 'Sabang', 'Sabang Beach Road, Puerto Galera, Oriental Mindoro', 'Close to the dive shops and the Sabang nightlife.', 13.5222, 120.9538, '+639170000003', 'staff.sabang@hotelgo.ph', 'Mia Santos',
                [['Garden Room', 'Double bed and fan, beside the garden.', 'Fan, Hot shower, Wi-Fi', 2, 550, 850, 1100, 1300], ['Reef Suite', 'Aircon suite with a balcony.', 'Aircon, Balcony, TV, Wi-Fi', 4, 1250, 1900, 2400, 2800]]],
            ['Talipanan Garden Inn', 'talipanan-garden-inn', 'Talipanan', 'Talipanan Beach, Puerto Galera, Oriental Mindoro', 'A quiet inn for families, far from the crowds.', 13.4870, 120.9031, '+639170000004', 'staff.talipanan@hotelgo.ph', 'Ben Reyes',
                [['Standard Double', 'Aircon room with a double bed.', 'Aircon, Hot shower', 2, 600, 900, 1150, 1400], ['Family Cottage', 'Two beds and a small kitchen.', '2 beds, Aircon, Kitchenette, Parking', 6, 1500, 2200, 2800, 3300]]],
            ['La Laguna Beach Lodge', 'la-laguna-beach-lodge', 'Big La Laguna', 'Big La Laguna Beach, Puerto Galera, Oriental Mindoro', 'Beachfront huts next to the quiet end of the bay.', 13.5068, 120.9762, '+639170000005', 'staff.laguna@hotelgo.ph', 'Ana Lim',
                [['Beach Hut', 'Cosy hut a few steps from the sand.', 'Fan, Hot shower', 2, 500, 800, 1000, 1200], ['Seaview Villa', 'Villa with a terrace facing the sea.', 'Aircon, Terrace, TV, Wi-Fi', 4, 1600, 2300, 2900, 3400]]],
        ];
        $ins = function (string $t, array $row) use ($db): int { $db->table($t)->insert($row); return (int) $db->last_id(); };
        $n = 0;
        foreach ($hotels as $h) {
            $hid = $ins('hotels', [
                'name' => $h[0], 'slug' => $h[1], 'area' => $h[2], 'address' => $h[3], 'description' => $h[4], 'lat' => $h[5], 'lng' => $h[6],
                'phone' => '+639708655964', 'email' => 'hotelgoph@gmail.com', 'is_active' => 1, 'created_at' => $now,
            ]);
            foreach ($h[10] as $i => $t) {
                $tid = $ins('room_types', [
                    'hotel_id' => $hid, 'name' => $t[0], 'description' => $t[1], 'amenities' => $t[2], 'max_guests' => $t[3],
                    'price_3h' => $t[4], 'price_6h' => $t[5], 'price_12h' => $t[6], 'price_overnight' => $t[7], 'cleaning_buffer_min' => 15, 'is_active' => 1,
                ]);
                for ($r = 1; $r <= 4; $r++) {
                    $db->table('rooms')->insert(['hotel_id' => $hid, 'room_no' => ($i + 1) . '0' . $r, 'room_type_id' => $tid, 'floor' => $i + 1, 'status' => 'active']);
                }
            }
            $db->table('users')->insert([
                'full_name' => $h[9], 'email' => $h[8], 'mobile' => $h[7], 'password_hash' => $hash, 'role' => 'staff', 'hotel_id' => $hid,
                'is_active' => 1, 'must_change_password' => 1, 'mobile_verified_at' => '2026-01-01 00:00:00', 'created_at' => '2026-01-01 00:00:00',
            ]);
            $n++;
        }
    }

    public function down()
    {
        $db = $this->_lava->db;
        foreach (['sabang-reef-lodge', 'talipanan-garden-inn', 'la-laguna-beach-lodge'] as $slug) {
            $h = $db->table('hotels')->select('id')->where('slug', $slug)->get();
            if (!$h) continue;
            $id = (int) $h['id'];
            $db->raw('DELETE FROM `users` WHERE `hotel_id` = ?', [$id]);
            $db->raw('DELETE FROM `rooms` WHERE `hotel_id` = ?', [$id]);
            $db->raw('DELETE FROM `room_types` WHERE `hotel_id` = ?', [$id]);
            $db->raw('DELETE FROM `hotels` WHERE `id` = ?', [$id]);
        }
    }
}
