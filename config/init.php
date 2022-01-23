<?php
/**
 * Warning:
 * Do not mess with this file if you don't know what you're dealing with
 */

//composer autoload
require __DIR__ . '/../vendor/autoload.php';

//dotenv for loading variables in .env as $_ENV
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '/../.env');
$dotenv->load();

//php vars file
require_once __DIR__.'/config.php';
