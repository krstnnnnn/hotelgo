<?php

/*
| Stays of several days. Each room type gets a fixed price PER DAY (room_types.price_daily); the guest chooses how many days.
| A day stay checks in at settings.daily_checkin and checks out at settings.daily_checkout of the last day.
|   price_daily  0 = the room type does not offer day stays.  Existing room types start with their overnight price.
|   max_stay_days   the most days one booking may have.
| Safe to run twice.
*/
class Daily_stays {

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
        $db = $this->_lava->db;
        if (!$this->has_column('room_types', 'price_daily')) {
            $this->_lava->dbforge->add_column('room_types', ['price_daily' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => FALSE, 'default' => 0]]);
            $db->raw('UPDATE `room_types` SET `price_daily` = `price_overnight`');       // a sensible start: one day costs what one night costs. The owner changes it in Room types.
        }
        foreach (['daily_checkin' => '14:00', 'daily_checkout' => '12:00', 'max_stay_days' => '30'] as $k => $v) {
            if ($db->table('settings')->where('skey', $k)->count() === 0) {
                $db->table('settings')->insert(['skey' => $k, 'svalue' => $v]);
            }
        }
    }

    public function down()
    {
        $this->_lava->db->table('settings')->in('skey', ['daily_checkin', 'daily_checkout', 'max_stay_days'])->delete();
        // price_daily stays (dropping a column is not portable); it is harmless
    }
}
