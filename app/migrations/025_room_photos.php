<?php

/*
| Room photo gallery. Each room type can have many pictures (the main picture stays in room_types.image_id).
|   room_photos   room_type_id, image_id (a row of the images table), sort_order
| Safe to run twice.
*/
class Room_photos {

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

    public function up()
    {
        $db = $this->_lava->db;
        if ($this->mysql()) {
            $db->raw('CREATE TABLE IF NOT EXISTS `room_photos` (
                `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `room_type_id` INT NOT NULL,
                `image_id` INT NOT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                UNIQUE KEY `uq_room_photos` (`room_type_id`, `image_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        } else {
            $db->raw('CREATE TABLE IF NOT EXISTS `room_photos` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `room_type_id` INTEGER NOT NULL,
                `image_id` INTEGER NOT NULL,
                `sort_order` INTEGER NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                UNIQUE (`room_type_id`, `image_id`))');
        }
    }

    public function down()
    {
        $this->_lava->db->raw('DROP TABLE IF EXISTS `room_photos`');
    }
}
