<?php

class Create_bookings_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('bookings')) {
            return;
        }
        $this->_lava->dbforge
            ->add_field([
                'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => TRUE],
                'booking_ref' => ['type' => 'VARCHAR', 'constraint' => 12],
                'user_id' => ['type' => 'INT', 'constraint' => 11, 'null' => TRUE, 'default' => NULL],
                'room_type_id' => ['type' => 'INT', 'constraint' => 11],
                'room_id' => ['type' => 'INT', 'constraint' => 11, 'null' => TRUE, 'default' => NULL],
                'stay_type' => ['type' => 'VARCHAR', 'constraint' => 10],
                'hours' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'start_at' => ['type' => 'DATETIME'],
                'end_at' => ['type' => 'DATETIME'],
                'guests' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'guest_name' => ['type' => 'VARCHAR', 'constraint' => 120],
                'guest_mobile' => ['type' => 'VARCHAR', 'constraint' => 20],
                'guest_email' => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
                'id_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => ''],
                'id_last4' => ['type' => 'VARCHAR', 'constraint' => 4, 'default' => ''],
                'room_rate' => ['type' => 'DECIMAL', 'constraint' => '10,2'],
                'booking_fee' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
                'total' => ['type' => 'DECIMAL', 'constraint' => '10,2'],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20],
                'pay_method' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => ''],
                'pay_ref' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => TRUE, 'default' => NULL],
                'source' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'online'],
                'hold_expires_at' => ['type' => 'DATETIME', 'null' => TRUE, 'default' => NULL],
                'created_at' => ['type' => 'DATETIME'],
                'cancelled_at' => ['type' => 'DATETIME', 'null' => TRUE, 'default' => NULL],
                'checked_in_at' => ['type' => 'DATETIME', 'null' => TRUE, 'default' => NULL],
                'checked_out_at' => ['type' => 'DATETIME', 'null' => TRUE, 'default' => NULL],
            ])
            ->add_key('id', primary: TRUE)
            ->add_key('booking_ref', unique: TRUE, name: 'booking_ref_unique')
            ->add_key(['room_id', 'start_at', 'end_at'], unique: FALSE, name: 'idx_bookings_room_time')
            ->add_key('user_id', unique: FALSE, name: 'idx_bookings_user')
            ->add_key('status', unique: FALSE, name: 'idx_bookings_status')
            ->add_foreign_key('user_id', 'users', 'id', 'RESTRICT', 'CASCADE')
            ->add_foreign_key('room_type_id', 'room_types', 'id', 'RESTRICT', 'CASCADE')
            ->add_foreign_key('room_id', 'rooms', 'id', 'RESTRICT', 'CASCADE')
            ->create_table('bookings');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('bookings');
    }
}
