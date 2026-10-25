<?php

class Create_settings_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('settings')) {
            return;
        }
        $this->_lava->dbforge
            ->add_field([
                'skey'   => ['type' => 'VARCHAR', 'constraint' => 60],
                'svalue' => ['type' => 'VARCHAR', 'constraint' => 255],
            ])
            ->add_key('skey', primary: TRUE)
            ->create_table('settings');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('settings');
    }
}
