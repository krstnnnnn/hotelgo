<?php

/*
| Real photos. Each uploaded picture is stored in the database (table: images), so it survives every
| Render deploy and needs no extra service. A hotel and a room type point to their photo with image_id.
| The pictures on the home page (hero, gallery ...) are saved in the settings table as photo_<slot> = image id.
*/
class Create_images_table {

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
        if ($this->mysql()) {
            $db->raw('CREATE TABLE IF NOT EXISTS `images` (
                `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `mime` VARCHAR(30) NOT NULL,
                `bytes` INT NOT NULL,
                `data` LONGTEXT NOT NULL,
                `created_at` DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        } else {
            $db->raw('CREATE TABLE IF NOT EXISTS `images` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `mime` VARCHAR(30) NOT NULL,
                `bytes` INT NOT NULL,
                `data` LONGTEXT NOT NULL,
                `created_at` DATETIME NOT NULL
            )');
        }
        foreach (['hotels', 'room_types'] as $t) {
            if (!$this->has_column($t, 'image_id')) {
                $this->_lava->dbforge->add_column($t, ['image_id' => ['type' => 'INT', 'constraint' => 11, 'null' => TRUE, 'default' => NULL]]);
            }
        }
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('images');
        // the image_id columns stay (dropping columns is not portable); they are harmless
    }
}
