<?php

class Create_room_types_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('room_types')) {
            return;
        }
        $this->_lava->dbforge
            ->add_field([
                'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => TRUE],
                'name' => ['type' => 'VARCHAR', 'constraint' => 80],
                'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => TRUE, 'default' => NULL],
                'amenities' => ['type' => 'VARCHAR', 'constraint' => 250, 'null' => TRUE, 'default' => NULL],
                'max_guests' => ['type' => 'INT', 'constraint' => 11, 'default' => 2],
                'price_3h' => ['type' => 'DECIMAL', 'constraint' => '10,2'],
                'price_6h' => ['type' => 'DECIMAL', 'constraint' => '10,2'],
                'price_12h' => ['type' => 'DECIMAL', 'constraint' => '10,2'],
                'price_overnight' => ['type' => 'DECIMAL', 'constraint' => '10,2'],
                'cleaning_buffer_min' => ['type' => 'INT', 'constraint' => 11, 'default' => 15],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 4, 'default' => 1],
            ])
            ->add_key('id', primary: TRUE)
            ->create_table('room_types');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('room_types');
    }
}
