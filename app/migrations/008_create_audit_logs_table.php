<?php

class Create_audit_logs_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('audit_logs')) {
            return;
        }
        $this->_lava->dbforge
            ->add_field([
                'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => TRUE],
                'user_id' => ['type' => 'INT', 'constraint' => 11, 'null' => TRUE, 'default' => NULL],
                'action' => ['type' => 'VARCHAR', 'constraint' => 60],
                'detail' => ['type' => 'VARCHAR', 'constraint' => 250, 'null' => TRUE, 'default' => NULL],
                'ip' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => TRUE, 'default' => NULL],
                'created_at' => ['type' => 'DATETIME'],
            ])
            ->add_key('id', primary: TRUE)
            ->create_table('audit_logs');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('audit_logs');
    }
}
