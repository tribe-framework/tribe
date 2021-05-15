<?php
$scss_file_path = '';
/*
Read Bootstrap SCSS guide at https://getbootstrap.com/docs/5.0/customize/sass/

To modify Bootstrap's default SCSS, read about custom.scss in https://getbootstrap.com/docs/5.0/customize/sass/#importing

To use SCSS modify path to scss file if required, uncomment the line below
 */

//$scss_file_path = __DIR__ . '/../../../node_modules/bootstrap/scss/bootstrap.scss';

if ($scss_file_path) {
	$sass = new Sass();
	$sass->setStyle(Sass::STYLE_COMPRESSED);
	$sass->setEmbed(true);
	$css = $sass->compileFile($scss_file_path);
	header("Content-Type: text/css; charset=utf-8");
	echo $css;
}