<?php

/*
| A guest who signs up with Google has no mobile number yet (they give it when they book), so users.mobile may be empty (NULL).
| The number is still unique: two empty values do not clash. New installs already have it this way (migration 001);
| this migration fixes databases that were created before. Safe to run twice.
*/
class Users_mobile_optional {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->database();
    }

    public function up()
    {
        $driver = ((database_config()['main']['driver'] ?? '') ?: 'mysql');
        if ($driver === 'mysql') {
            $this->_lava->db->raw('ALTER TABLE `users` MODIFY `mobile` VARCHAR(20) NULL');
        }
    }

    public function down()
    {
        // stays optional: guests who signed up with Google have no number
    }
}
