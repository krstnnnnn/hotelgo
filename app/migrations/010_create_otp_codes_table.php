<?php

class Create_otp_codes_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('otp_codes')) {
            return;
        }
        $this->_lava->dbforge
            ->add_field([
                'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => TRUE],
                'user_id' => ['type' => 'INT', 'constraint' => 11],
                'purpose' => ['type' => 'VARCHAR', 'constraint' => 10],
                'code_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
                'expires_at' => ['type' => 'DATETIME'],
                'attempts' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'used_at' => ['type' => 'DATETIME', 'null' => TRUE, 'default' => NULL],
                'created_at' => ['type' => 'DATETIME'],
            ])
            ->add_key('id', primary: TRUE)
            ->create_table('otp_codes');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('otp_codes');
    }
}
