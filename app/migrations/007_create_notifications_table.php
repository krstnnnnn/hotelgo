<?php

class Create_notifications_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('notifications')) {
            return;
        }
        $this->_lava->dbforge
            ->add_field([
                'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => TRUE],
                'booking_id' => ['type' => 'INT', 'constraint' => 11, 'null' => TRUE, 'default' => NULL],
                'user_id' => ['type' => 'INT', 'constraint' => 11, 'null' => TRUE, 'default' => NULL],
                'channel' => ['type' => 'VARCHAR', 'constraint' => 10],
                'recipient' => ['type' => 'VARCHAR', 'constraint' => 150],
                'subject' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => TRUE, 'default' => NULL],
                'message' => ['type' => 'TEXT'],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20],
                'provider_ref' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => TRUE, 'default' => NULL],
                'error' => ['type' => 'VARCHAR', 'constraint' => 250, 'null' => TRUE, 'default' => NULL],
                'created_at' => ['type' => 'DATETIME'],
            ])
            ->add_key('id', primary: TRUE)
            ->create_table('notifications');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('notifications');
    }
}
