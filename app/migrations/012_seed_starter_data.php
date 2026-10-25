<?php

/*
| Starter data: motel settings, room types, rooms, and the first owner + front-desk accounts.
| Temporary password for both accounts: ChangeMe!2026  (they MUST change it at first sign-in).
*/
class Seed_starter_data {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->database();
    }

    public function up()
    {
        $db = $this->_lava->db;
        if ($db->table('room_types')->count() > 0) {
            return;     // already seeded
        }

        foreach ([
            'motel_name' => 'Coral Nest Motel', 'motel_address' => 'White Beach, Puerto Galera, Oriental Mindoro',
            'motel_lat' => '13.5188', 'motel_lng' => '120.9647', 'booking_fee' => '30', 'hold_minutes' => '10',
            'cancel_hours' => '2', 'overnight_start' => '20:00', 'overnight_end' => '12:00',
        ] as $k => $v) {
            $db->table('settings')->insert(['skey' => $k, 'svalue' => $v]);
        }

        $types = [
            ['Standard Queen', 'Queen bed, aircon and hot shower.', 'Aircon, Hot shower, Wi-Fi', 2, 650, 1000, 1250, 1500, 15],
            ['Deluxe Sea View', 'Queen bed, private balcony and a window facing the bay.', 'Aircon, Balcony, Parking, Wi-Fi', 3, 950, 1400, 1800, 2100, 15],
            ['Family Suite', 'Two beds, TV and extra space for a family.', '2 beds, Aircon, TV', 5, 1450, 2100, 2700, 3200, 20],
            ['Executive Suite', 'Large suite with a living area and mini fridge.', 'Aircon, TV, Mini fridge, Parking', 4, 1850, 2600, 3300, 3800, 20],
        ];
        foreach ($types as $t) {
            $db->table('room_types')->insert([
                'name' => $t[0], 'description' => $t[1], 'amenities' => $t[2], 'max_guests' => $t[3],
                'price_3h' => $t[4], 'price_6h' => $t[5], 'price_12h' => $t[6], 'price_overnight' => $t[7], 'cleaning_buffer_min' => $t[8],
            ]);
        }

        // 6 rooms per floor, floor n uses room type n (ids 1-4 were just created in order)
        $typeIds = array_column($db->table('room_types')->select('id')->order_by('id', 'ASC')->get_all(), 'id');
        foreach ($typeIds as $i => $typeId) {
            $floor = $i + 1;
            for ($n = 1; $n <= 6; $n++) {
                $db->table('rooms')->insert(['room_no' => $floor . '0' . $n, 'room_type_id' => $typeId, 'floor' => $floor]);
            }
        }

        $hash = '$2y$10$vxyXlANjBTeIOTm7ij4wVO8GHECwFLqgg5tVKkhpt800.Rn8TH8Gi';
        foreach ([
            ['HOTELGO Admin', 'admin@hotelgo.ph', '+639170000001', 'admin'],
            ['Rico Cruz', 'rico@hotelgo.ph', '+639170000002', 'staff'],
        ] as $u) {
            $db->table('users')->insert([
                'full_name' => $u[0], 'email' => $u[1], 'mobile' => $u[2], 'password_hash' => $hash, 'role' => $u[3],
                'is_active' => 1, 'must_change_password' => 1, 'mobile_verified_at' => '2026-01-01 00:00:00', 'created_at' => '2026-01-01 00:00:00',
            ]);
        }
    }

    public function down()
    {
        $db = $this->_lava->db;
        foreach (['rooms', 'room_types', 'settings'] as $t) { $db->raw("DELETE FROM `$t`"); }
        $db->raw("DELETE FROM `users` WHERE `email` IN ('admin@hotelgo.ph','rico@hotelgo.ph')");
    }
}
