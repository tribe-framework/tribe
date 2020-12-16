<?php
//composer autoload
require __DIR__ . '/vendor/autoload.php';

//dotenv for loading variables in tribe.var.env as $_ENV
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '.env');
$dotenv->load();

//php vars file
include_once __DIR__ . '/config/config.php';

//wildfire core classes
$init = new Wildfire\Core\Init();
$init->init();
