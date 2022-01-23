<?php
/**
 * Warning:
 * Do not mess with this file if you don't know what you're dealing with
 */

//composer autoload
require __DIR__ . '/vendor/autoload.php';

//dotenv for loading variables in .env as $_ENV
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '.env');
$dotenv->load();

//php vars file
require_once __DIR__.'/config/config.php';

function tribe_error_handler()
{
    $last_error = error_get_last();

    if (!($last_error && $last_error['type'] == E_ERROR)) {
        return;
    }

    header("HTTP/1.1 500 Internal Server Error");
    if (\file_exists(THEME_PATH.'/pages/500.php')) {
        require_once THEME_PATH.'/pages/500.php';
    } else {
        echo "Internal server error";
    }
}

// critical error handler
register_shutdown_function('tribe_error_handler');
