<?php

/*
| VIP rooms and service requests.
|   room_types.is_vip              1 = a VIP / luxury room: a normal-looking room with a fixed higher price and a package of services already included
|   room_types.included_services   that package, one service per line ("Title: details")
|   service_requests               what a guest (or the front desk) asks for during a stay: private dining, karaoke, balcony set-up ...
|                                  new > accepted (staff may set a price) > done, or declined. An extra price is paid in cash or by PayMongo link.
| The first hotel also gets one VIP suite as a starting example.
*/
class Vip_rooms_and_service_requests {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
        $this->_lava->call->database();
    }

    private function mysql(): bool
    {
        return (((database_config()['main']['driver'] ?? '') ?: 'mysql') === 'mysql');
    }

    /* Does the table already have this column? (ask the catalogue: a failed query would stop the whole run) */
    private function has_column(string $table, string $col): bool
    {
        if ($this->mysql()) {
            $r = $this->_lava->db->raw('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $col]);
            return (int) $r->fetchColumn() > 0;
        }
        foreach ($this->_lava->db->raw("PRAGMA table_info(`$table`)")->fetchAll(PDO::FETCH_ASSOC) as $c) {
            if ($c['name'] === $col) return true;
        }
        return false;
    }

    const VIP_PACKAGE = [
        'Arrival & escort: direct welcome greeting, priority check-in, personal escort to the room and a welcome note from management',
        'Upgraded room amenities: luxury bathrobes, slippers, upgraded towel sets, specialty toiletries and a pillow menu',
        'Complimentary refreshments: fruit basket, chocolates or cookies, fresh flowers and a stocked mini-bar',
        'Dedicated assistance: 24/7 butler service, daily linen changes, priority room service and fast-track checkout or in-room billing',
        'Private dining room: an enclosed space for small gatherings, meetings or celebrations, with a private audio-visual or karaoke system',
        'Personalized hospitality: a dedicated server, a custom tasting menu from the chef, welcome drinks or dessert',
        'Free Wi-Fi, private restroom access and flexible time allowances for the reservation',
    ];

    public function up()
    {
        $db = $this->_lava->db;
        $dbf = $this->_lava->dbforge;

        if (!$this->has_column('room_types', 'is_vip')) {
            $dbf->add_column('room_types', ['is_vip' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => FALSE, 'default' => 0]]);
        }
        if (!$this->has_column('room_types', 'included_services')) {
            $dbf->add_column('room_types', ['included_services' => ['type' => 'TEXT', 'null' => TRUE]]);
        }

        if ($this->mysql()) {
            $db->raw('CREATE TABLE IF NOT EXISTS `service_requests` (
                `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `booking_id` INT NOT NULL,
                `hotel_id` INT NULL,
                `service` VARCHAR(120) NOT NULL,
                `note` VARCHAR(500) NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT \'new\',
                `price` DECIMAL(10,2) NULL,
                `pay_ref` VARCHAR(80) NULL,
                `paid_at` DATETIME NULL,
                `source` VARCHAR(10) NOT NULL DEFAULT \'guest\',
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL,
                `handled_by` INT NULL,
                INDEX `idx_requests_hotel_status` (`hotel_id`, `status`),
                INDEX `idx_requests_booking` (`booking_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        } else {
            $db->raw('CREATE TABLE IF NOT EXISTS `service_requests` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `booking_id` INT NOT NULL,
                `hotel_id` INT NULL,
                `service` VARCHAR(120) NOT NULL,
                `note` VARCHAR(500) NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT \'new\',
                `price` DECIMAL(10,2) NULL,
                `pay_ref` VARCHAR(80) NULL,
                `paid_at` DATETIME NULL,
                `source` VARCHAR(10) NOT NULL DEFAULT \'guest\',
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL,
                `handled_by` INT NULL
            )');
            $db->raw('CREATE INDEX IF NOT EXISTS idx_requests_hotel_status ON service_requests (hotel_id, status)');
            $db->raw('CREATE INDEX IF NOT EXISTS idx_requests_booking ON service_requests (booking_id)');
        }

        // a starting example: one VIP suite with two rooms in the first hotel (only when no VIP room exists yet)
        $hotel = $db->table('hotels')->select('id')->order_by('id', 'ASC')->limit(1)->get();
        if ($hotel && $db->table('room_types')->where('is_vip', 1)->count() === 0) {
            $hid = (int) $hotel['id'];
            $tid = (int) $db->table('room_types')->insert([
                'hotel_id' => $hid, 'name' => 'VIP Luxury Suite',
                'description' => 'Our most complete suite: the comfort of a normal room, upgraded, with VIP services already included.',
                'amenities' => 'Aircon, TV, Mini bar, Balcony, Wi-Fi', 'max_guests' => 4,
                'price_3h' => 2800, 'price_6h' => 3900, 'price_12h' => 4800, 'price_overnight' => 5600, 'cleaning_buffer_min' => 20, 'is_active' => 1,
                'is_vip' => 1, 'included_services' => implode("\n", self::VIP_PACKAGE),
            ]);
            if ($tid > 0) {
                foreach (['VIP1', 'VIP2'] as $no) {
                    $db->table('rooms')->insert(['hotel_id' => $hid, 'room_no' => $no, 'room_type_id' => $tid, 'floor' => 3, 'status' => 'active']);
                }
            }
        }
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('service_requests');
        // is_vip / included_services stay (dropping columns is not portable); they are harmless
    }
}
