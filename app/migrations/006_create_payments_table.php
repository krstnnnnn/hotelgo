<?php

class Create_payments_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('payments')) {
            return;
        }
        $this->_lava->dbforge
            ->add_field([
                'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => TRUE],
                'booking_id' => ['type' => 'INT', 'constraint' => 11],
                'txn_ref' => ['type' => 'VARCHAR', 'constraint' => 40],
                'method' => ['type' => 'VARCHAR', 'constraint' => 20],
                'provider' => ['type' => 'VARCHAR', 'constraint' => 20],
                'provider_ref' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => TRUE, 'default' => NULL],
                'amount' => ['type' => 'DECIMAL', 'constraint' => '10,2'],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20],
                'created_at' => ['type' => 'DATETIME'],
                'paid_at' => ['type' => 'DATETIME', 'null' => TRUE, 'default' => NULL],
                'refunded_at' => ['type' => 'DATETIME', 'null' => TRUE, 'default' => NULL],
            ])
            ->add_key('id', primary: TRUE)
            ->add_key('txn_ref', unique: TRUE, name: 'txn_ref_unique')
            ->add_foreign_key('booking_id', 'bookings', 'id', 'RESTRICT', 'CASCADE')
            ->create_table('payments');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('payments');
    }
}
