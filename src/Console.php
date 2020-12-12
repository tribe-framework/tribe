<?php

namespace DEF;

class Console
{
    public static function log ($data, $die = false) {
        echo '<pre>';
        print_r($data);
        echo '</pre>';

        if ($die) {
            die();
        }
    }
}
