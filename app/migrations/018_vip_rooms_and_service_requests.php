<?php

/*
| VIP rooms and service requests.
|   room_types.is_vip              1 = a VIP / luxury room: a normal-looking room with a fixed higher price and a package of services already included
|   room_types.included_services   that package, one service per line ("Title: details")
|   service_requests               what a guest (or the front desk) asks for during a stay: private dining, karaoke, balcony set-up ...
|                                  new > accepted (staff may set a price) > done, or declined. An extra price is paid in cash or by PayMongo link.
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
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('service_requests');
        // is_vip / included_services stay (dropping columns is not portable); they are harmless
    }
}
