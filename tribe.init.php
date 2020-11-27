<?php
//composer autoload
require __DIR__ . '/vendor/autoload.php';

//dotenv for loading variables in tribe.var.env as $_ENV
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, 'tribe.var.env');
$dotenv->load();

//php vars file
include_once __DIR__ . '/config/vars.php';

//wildfire core classes
include_once __DIR__ . '/vendor/wildfire/core/init.php';
?>