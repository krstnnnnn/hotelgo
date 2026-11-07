<?php

/*
| Guest preferences on a booking (the "Special requests" part of checkout).
|   bookings.preferences      JSON: room type (non_smoking | smoking), bed (large | twin), extra preferences (a list)
|   bookings.special_requests the guest's own note, up to 500 characters
|   bookings.arrival_eta      the expected arrival time (unknown | 00:00 | 12:00 ... 23:00)
| Safe to run twice.
*/
class Booking_requests {

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
        if (!$this->has_column('bookings', 'preferences')) {
            $this->_lava->dbforge->add_column('bookings', ['preferences' => ['type' => 'TEXT', 'null' => TRUE, 'default' => NULL]]);
        }
        if (!$this->has_column('bookings', 'special_requests')) {
            $this->_lava->dbforge->add_column('bookings', ['special_requests' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => TRUE, 'default' => NULL]]);
        }
        if (!$this->has_column('bookings', 'arrival_eta')) {
            $this->_lava->dbforge->add_column('bookings', ['arrival_eta' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => TRUE, 'default' => NULL]]);
        }
    }

    public function down()
    {
        // the columns stay (dropping a column is not portable); they are harmless
    }
}
