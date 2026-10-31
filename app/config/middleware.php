<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/* Every middleware must be listed here. The key is the "alias" used in routes.php */
/* No route middleware: this server is JSON only and every API controller checks the bearer token itself (app/support/ApiBase.php). */
$config['middlewares'] = array();
