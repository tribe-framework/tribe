<?php
//composer autoload
require __DIR__ . '/vendor/autoload.php';

define('ROOT_DIR', __DIR__);

//dotenv for loading variables in tribe.var.env as $_ENV
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '.env');
$dotenv->load();

date_default_timezone_set($_ENV['DEFAULT_TIMEZONE']);

//php vars file
include_once __DIR__.'/config/config.php';

// initialising Wildfire Core
$kernel = new Wildfire\Core\Kernel();
$kernel->init();
