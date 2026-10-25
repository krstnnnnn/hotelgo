<?php

class Create_rooms_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('rooms')) {
            return;
        }
        $this->_lava->dbforge
            ->add_field([
                'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => TRUE],
                'room_no' => ['type' => 'VARCHAR', 'constraint' => 10],
                'room_type_id' => ['type' => 'INT', 'constraint' => 11],
                'floor' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            ])
            ->add_key('id', primary: TRUE)
            ->add_key('room_no', unique: TRUE, name: 'room_no_unique')
            ->add_foreign_key('room_type_id', 'room_types', 'id', 'RESTRICT', 'CASCADE')
            ->create_table('rooms');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('rooms');
    }
}
