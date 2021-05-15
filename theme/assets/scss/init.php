<?php
$sass = new Sass();
$sass->setStyle(Sass::STYLE_COMPRESSED);
$sass->setEmbed(true);

//to use SCSS uncomment the line below and modify path to scss file if required
//$css = $sass->compileFile(__DIR__ . '/bootstrap/bootstrap.scss');

if ($css) {
	header("Content-Type: text/css; charset=utf-8");
	echo $css;
}