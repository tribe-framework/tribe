<?php
require __DIR__ . '/_init.php';

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
