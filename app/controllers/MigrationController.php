<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Database migrations (Laboratory: Database Migration Using LavaLust).
| Run them from the terminal:   php lava migration run | status | rollback | rollback-all | refresh
| The browser routes (/migrate, /status ...) exist for the lab, but they change the database,
| so they only answer in the terminal, or on your own computer when MIGRATION_WEB=1 is in .env.
| On the real website they show a 404 page.
*/
class MigrationController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        if (php_sapi_name() !== 'cli' && getenv('MIGRATION_WEB') !== '1') {
            abort(404);
        }
        $this->call->library('migration');
    }

    public function create_migration($migration_class)
    {
        $this->migration->create_migration($migration_class);
    }

    public function migrate()
    {
        $this->migration->migrate();
    }

    public function rollback()
    {
        $this->migration->rollback();
    }

    public function rollback_all()
    {
        $this->migration->rollback_all();
    }

    public function refresh()
    {
        $this->migration->refresh();
    }

    public function status()
    {
        $this->migration->status();
    }
}
