<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/* Every middleware must be listed here. The key is the "alias" used in routes.php */
$config['middlewares'] = array(
    'csrf'  => load_class('CsrfMiddleware',  'middlewares'),
    'auth'  => load_class('AuthMiddleware',  'middlewares'),
    'guest' => load_class('GuestMiddleware', 'middlewares'),
    'staff' => load_class('StaffMiddleware', 'middlewares'),
    'admin' => load_class('AdminMiddleware', 'middlewares'),
);
