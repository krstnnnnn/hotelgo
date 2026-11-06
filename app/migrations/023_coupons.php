<?php

/*
| Coupons / promo codes (like a first-time-guest voucher or a holiday promo).
|   coupons            one row per code: percent or fixed discount, who may use it, when, how often
|   bookings.coupon_code, bookings.discount   the code used on a booking and how many pesos it took off the room rate
| Safe to run twice.
*/
class Coupons {

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
        $cols = '`hotel_id` INT NULL,
                `code` VARCHAR(20) NOT NULL UNIQUE,
                `title` VARCHAR(80) NOT NULL,
                `kind` VARCHAR(8) NOT NULL DEFAULT \'percent\',
                `value` DECIMAL(10,2) NOT NULL,
                `max_discount` DECIMAL(10,2) NOT NULL DEFAULT 0,
                `min_spend` DECIMAL(10,2) NOT NULL DEFAULT 0,
                `first_booking_only` TINYINT NOT NULL DEFAULT 0,
                `starts_on` DATE NULL,
                `ends_on` DATE NULL,
                `stay_from` DATE NULL,
                `stay_to` DATE NULL,
                `max_uses` INT NOT NULL DEFAULT 0,
                `per_user_limit` INT NOT NULL DEFAULT 1,
                `is_public` TINYINT NOT NULL DEFAULT 1,
                `is_active` TINYINT NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL';
        if ($this->mysql()) {
            $db->raw('CREATE TABLE IF NOT EXISTS `coupons` (`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY, ' . $cols . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        } else {
            $db->raw('CREATE TABLE IF NOT EXISTS `coupons` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, ' . $cols . ')');
        }
        if (!$this->has_column('bookings', 'coupon_code')) {
            $this->_lava->dbforge->add_column('bookings', ['coupon_code' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => TRUE, 'default' => NULL]]);
        }
        if (!$this->has_column('bookings', 'discount')) {
            $this->_lava->dbforge->add_column('bookings', ['discount' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => FALSE, 'default' => 0]]);
        }
    }

    public function down()
    {
        $this->_lava->db->raw('DROP TABLE IF EXISTS `coupons`');
        // the two booking columns stay (dropping a column is not portable); they are harmless
    }
}
