<?php
require __DIR__ . '/config/init.php';

function tribe_error_handler()
{
    $last_error = error_get_last();

    if (!($last_error && $last_error['type'] == E_ERROR)) {
        return;
    }

    header("HTTP/1.1 500 Internal Server Error");
    if (\file_exists(__DIR__.'/theme/error_pages/error_500.php')) {
        require_once __DIR__.'/theme/error_pages/error_500.php';
    } else {
        echo "Internal server error";
    }
}

// critical error handler
register_shutdown_function('tribe_error_handler');

// initialize tribe framework
try {
    new \Wildfire\Core\Init();
} catch (\Throwable $t) {
    $last_error = error_get_last();

    if ('dev' == strtolower(($_ENV['ENV']))) {
        \Wildfire\Core\Console::debug($last_error ?? $t);
    }
} catch (\Exception $e) {
    $last_error = error_get_last();

    if ('dev' == strtolower(($_ENV['ENV']))) {
        \Wildfire\Core\Console::debug($last_error ?? $e);
    }
}
