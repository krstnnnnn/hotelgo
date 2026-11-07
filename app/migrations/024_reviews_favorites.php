<?php

/*
| Ratings and saved hotels.
|   reviews     one rating per booking (1 to 5 stars and an optional comment), written by the guest after the stay has ended.
|   favorites   the hotels a signed-in guest saved (the heart on the room list). One row per guest and hotel.
| Safe to run twice.
*/
class Reviews_favorites {

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
            $db->raw('CREATE TABLE IF NOT EXISTS `reviews` (
                `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `booking_id` INT NOT NULL UNIQUE,
                `user_id` INT NOT NULL,
                `hotel_id` INT NOT NULL,
                `rating` TINYINT NOT NULL,
                `comment` VARCHAR(600) NULL,
                `is_visible` TINYINT NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL,
                INDEX `idx_reviews_hotel` (`hotel_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $db->raw('CREATE TABLE IF NOT EXISTS `favorites` (
                `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `hotel_id` INT NOT NULL,
                `created_at` DATETIME NOT NULL,
                UNIQUE KEY `uq_favorites_user_hotel` (`user_id`, `hotel_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        } else {
            $db->raw('CREATE TABLE IF NOT EXISTS `reviews` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `booking_id` INTEGER NOT NULL UNIQUE,
                `user_id` INTEGER NOT NULL,
                `hotel_id` INTEGER NOT NULL,
                `rating` INTEGER NOT NULL,
                `comment` VARCHAR(600) NULL,
                `is_visible` INTEGER NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL)');
            $db->raw('CREATE TABLE IF NOT EXISTS `favorites` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `user_id` INTEGER NOT NULL,
                `hotel_id` INTEGER NOT NULL,
                `created_at` DATETIME NOT NULL,
                UNIQUE (`user_id`, `hotel_id`))');
        }
    }

    public function down()
    {
        $this->_lava->db->raw('DROP TABLE IF EXISTS `reviews`');
        $this->_lava->db->raw('DROP TABLE IF EXISTS `favorites`');
    }
}
