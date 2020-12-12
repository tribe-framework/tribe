<?php

function error404()
{
    header("HTTP/1.0 404 Not Found");
    die();
}

if (!$_GET['file']) {
    error404();
}

$sass = new Sass();
$sass->setStyle(Sass::STYLE_COMPRESSED);
$sass->setEmbed(true);

$sass_file = $_GET['file'] ?? null;
$sass_dir = THEME_PATH.'/assets/scss/';

/**
 * if requested file is "bootstrap" then load bootstrap.scss
 * otherwise load .scss file from "scss/"
 */
if ($sass_file == 'bootstrap') {
    $css = $sass->compileFile($sass_dir.'bootstrap/bootstrap.scss');
} elseif ($sass_file == 'fontawesome') {}else {
    $sass_file = $sass_dir.$sass_file.'.scss';

    if (file_exists($sass_file)) {
        $css = $sass->compileFile($sass_file);
    }
}

if ($css) {
    header("Content-Type: text/css; charset=utf-8");
    echo $css;
}

error404();
