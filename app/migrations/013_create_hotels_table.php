<?php

/*
| HOTELGO is one platform for MANY hotels in Puerto Galera. This table lists them.
| The first hotel is copied from the old single-motel settings, so nothing existing is lost.
*/
class Create_hotels_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
        $this->_lava->call->database();
    }

    public function up()
    {
        if (!$this->_lava->dbforge->table_exists('hotels')) {
            $this->_lava->dbforge
                ->add_field([
                    'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => TRUE],
                    'name' => ['type' => 'VARCHAR', 'constraint' => 120],
                    'slug' => ['type' => 'VARCHAR', 'constraint' => 80],
                    'area' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => TRUE, 'default' => NULL],
                    'address' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => TRUE, 'default' => NULL],
                    'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => TRUE, 'default' => NULL],
                    'lat' => ['type' => 'DECIMAL', 'constraint' => '10,6', 'default' => 13.5188],
                    'lng' => ['type' => 'DECIMAL', 'constraint' => '10,6', 'default' => 120.9647],
                    'phone' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => TRUE, 'default' => NULL],
                    'email' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => TRUE, 'default' => NULL],
                    'is_active' => ['type' => 'TINYINT', 'constraint' => 4, 'default' => 1],
                    'created_at' => ['type' => 'DATETIME'],
                ])
                ->add_key('id', primary: TRUE)
                ->add_key('slug', unique: TRUE, name: 'hotel_slug_unique')
                ->create_table('hotels');
        }

        $db = $this->_lava->db;
        if ($db->table('hotels')->count() > 0) {
            return;
        }
        $s = [];
        try {
            foreach ($db->table('settings')->get_all() as $r) { $s[$r['skey']] = $r['svalue']; }
        } catch (Throwable $e) { /* no settings yet */ }
        $db->table('hotels')->insert([
            'name' => $s['motel_name'] ?? 'Coral Nest Motel', 'slug' => 'coral-nest-motel', 'area' => 'White Beach',
            'address' => $s['motel_address'] ?? 'White Beach, Puerto Galera, Oriental Mindoro',
            'description' => 'Simple, clean rooms a short walk from the beach.',
            'lat' => (float) ($s['motel_lat'] ?? 13.5188), 'lng' => (float) ($s['motel_lng'] ?? 120.9647),
            'phone' => '+639708655964', 'email' => 'hotelgoph@gmail.com', 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('hotels');
    }
}
