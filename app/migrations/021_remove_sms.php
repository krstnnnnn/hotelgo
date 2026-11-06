<?php

/*
| HOTELGO sends email only (SMS was removed). This deletes any old "sms" rows from the notifications log
| so the Notifications screen shows email only. Safe to run twice.
*/
class Remove_sms {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->database();
    }

    public function up()
    {
        $this->_lava->db->raw("DELETE FROM `notifications` WHERE `channel` = 'sms'");
    }

    public function down()
    {
        // the deleted log rows are not restored
    }
}
