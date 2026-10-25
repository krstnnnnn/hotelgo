<?php

class Create_users_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('users')) {
            return;
        }
        $this->_lava->dbforge
            ->add_field([
                'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => TRUE],
                'full_name' => ['type' => 'VARCHAR', 'constraint' => 120],
                'email' => ['type' => 'VARCHAR', 'constraint' => 150],
                'mobile' => ['type' => 'VARCHAR', 'constraint' => 20],
                'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
                'role' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'guest'],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 4, 'default' => 1],
                'must_change_password' => ['type' => 'TINYINT', 'constraint' => 4, 'default' => 0],
                'mobile_verified_at' => ['type' => 'DATETIME', 'null' => TRUE, 'default' => NULL],
                'staff_note' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => TRUE, 'default' => NULL],
                'last_login_at' => ['type' => 'DATETIME', 'null' => TRUE, 'default' => NULL],
                'created_at' => ['type' => 'DATETIME'],
            ])
            ->add_key('id', primary: TRUE)
            ->add_key('email', unique: TRUE, name: 'email_unique')
            ->add_key('mobile', unique: TRUE, name: 'mobile_unique')
            ->create_table('users');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('users');
    }
}
