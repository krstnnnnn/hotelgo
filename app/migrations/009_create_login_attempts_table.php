<?php

class Create_login_attempts_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('login_attempts')) {
            return;
        }
        $this->_lava->dbforge
            ->add_field([
                'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => TRUE],
                'email' => ['type' => 'VARCHAR', 'constraint' => 150],
                'ip' => ['type' => 'VARCHAR', 'constraint' => 45],
                'success' => ['type' => 'TINYINT', 'constraint' => 4],
                'created_at' => ['type' => 'DATETIME'],
            ])
            ->add_key('id', primary: TRUE)
            ->add_key(['email', 'created_at'], unique: FALSE, name: 'idx_attempts_email')
            ->create_table('login_attempts');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('login_attempts');
    }
}
