<?php
$sass = new Sass();
$sass->setStyle(Sass::STYLE_COMPRESSED);
$sass->setEmbed(true);

$sass_file = $_GET['file'] ?? null;

/**
 * if requested file is "bootstrap" then load bootstrap.scss
 * otherwise load .scss file from "scss/"
 */
if ($sass_file == 'bootstrap') {
	$css = $sass->compileFile(__DIR__ . '/bootstrap/bootstrap.scss');
}

if ($css) {
	header("Content-Type: text/css; charset=utf-8");
	echo $css;
}
