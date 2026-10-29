<?php

/*
| Google Calendar sync.
|   hotels.calendar_id        the Google Calendar of that hotel (its Calendar ID from Google Calendar > Settings)
|   bookings.gcal_event_id    the calendar event that belongs to the booking (empty = not in the calendar)
|   bookings.gcal_synced_at   when the event was last written
*/
class Add_google_calendar_columns {

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
        $add = [
            ['hotels',   'calendar_id',    ['type' => 'VARCHAR', 'constraint' => 190, 'null' => TRUE, 'default' => NULL]],
            ['bookings', 'gcal_event_id',  ['type' => 'VARCHAR', 'constraint' => 64,  'null' => TRUE, 'default' => NULL]],
            ['bookings', 'gcal_synced_at', ['type' => 'DATETIME', 'null' => TRUE, 'default' => NULL]],
        ];
        foreach ($add as [$table, $col, $def]) {
            if (!$this->has_column($table, $col)) {
                $this->_lava->dbforge->add_column($table, [$col => $def]);
            }
        }
    }

    public function down()
    {
        // the columns stay (dropping columns is not portable); they are harmless
    }
}
