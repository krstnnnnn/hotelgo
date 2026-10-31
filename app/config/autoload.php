<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/* Loaded automatically on every request. */
$autoload['libraries'] = array('database');
$autoload['helpers']   = array('url', 'security', 'hotelgo');
$autoload['models']    = array();
$autoload['configs']   = array('middleware', 'hotelgo', 'integrations');
