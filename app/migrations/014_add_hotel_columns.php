<?php

/*
| Every room type, room, booking and staff account now belongs to a hotel (hotel_id).
| Existing rows are given to the first hotel. Room numbers only have to be unique INSIDE one hotel.
| An admin with no hotel (hotel_id empty) is the platform owner and sees every hotel.
*/
class Add_hotel_columns {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
        $this->_lava->call->database();
    }

    /* Does the table already have this column? (a failed query would stop the whole run, so ask the catalogue instead) */
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

    private function mysql(): bool
    {
        return (((database_config()['main']['driver'] ?? '') ?: 'mysql') === 'mysql');
    }

    public function up()
    {
        $db = $this->_lava->db;
        foreach (['room_types', 'rooms', 'bookings', 'users'] as $t) {
            if (!$this->has_column($t, 'hotel_id')) {
                $this->_lava->dbforge->add_column($t, ['hotel_id' => ['type' => 'INT', 'constraint' => 11, 'null' => TRUE, 'default' => NULL]]);
            }
        }

        $first = $db->table('hotels')->select('id')->order_by('id', 'ASC')->limit(1)->get();
        if ($first) {
            $id = (int) $first['id'];
            foreach (['room_types', 'rooms', 'bookings'] as $t) {
                $db->raw("UPDATE `$t` SET `hotel_id` = ? WHERE `hotel_id` IS NULL", [$id]);
            }
            $db->raw("UPDATE `users` SET `hotel_id` = ? WHERE `hotel_id` IS NULL AND `role` = 'staff'", [$id]);
        }

        // room numbers: unique per hotel instead of unique in the whole system
        if ($this->mysql()) {
            $keys = $db->raw("SHOW INDEX FROM `rooms`")->fetchAll(PDO::FETCH_ASSOC);
            $by = [];
            foreach ($keys as $k) { $by[$k['Key_name']][] = $k; }
            foreach ($by as $name => $cols) {
                if ($name !== 'PRIMARY' && count($cols) === 1 && (int) $cols[0]['Non_unique'] === 0 && $cols[0]['Column_name'] === 'room_no') {
                    $db->raw("ALTER TABLE `rooms` DROP INDEX `$name`");
                }
            }
        } else {
            $db->raw('DROP INDEX IF EXISTS `room_no_unique`');
        }
        $db->raw('CREATE UNIQUE INDEX `hotel_room_no_unique` ON `rooms` (`hotel_id`, `room_no`)');
        $db->raw('CREATE INDEX `room_types_hotel` ON `room_types` (`hotel_id`)');
        $db->raw('CREATE INDEX `bookings_hotel` ON `bookings` (`hotel_id`)');
    }

    public function down()
    {
        $db = $this->_lava->db;
        $db->raw('DROP INDEX `hotel_room_no_unique` ' . ($this->mysql() ? 'ON `rooms`' : ''));
        $db->raw('DROP INDEX `room_types_hotel` ' . ($this->mysql() ? 'ON `room_types`' : ''));
        $db->raw('DROP INDEX `bookings_hotel` ' . ($this->mysql() ? 'ON `bookings`' : ''));
        // the hotel_id columns stay (dropping columns is not portable); they are harmless
    }
}
