<?php
//session
session_save_path('/tmp');
session_start();

//composer autoload
require __DIR__ . '/vendor/autoload.php';

//dotenv for loading variables in tribe.var.env as $_ENV
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, 'tribe.var.env');
$dotenv->load();

//imported from wildfire-core
$sql = new Tribe\MySQL();
$dash = new Tribe\Dashboard();
$theme = new Tribe\Theme();

/*
$session_user = $_SESSION['user'] ?? NULL;

include_once __DIR__ . '/config/vars.php';

$types=$dash->get_types(ABSOLUTE_PATH.'/config/types.json');
$menus=json_decode(file_get_contents(ABSOLUTE_PATH.'/config/menus.json'), true);

// browser debugging
if (defined('ENV') && (ENV == 'dev')) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
}

require __DIR__ . '/vendor/autoload.php';
?>