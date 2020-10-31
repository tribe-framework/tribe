<?php
//session
session_save_path('/tmp');
session_start();

//composer autoload
require __DIR__ . '/vendor/autoload.php';

//dotenv for loading variables in tribe.var.env as $_ENV
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, 'tribe.var.env');
$dotenv->load();