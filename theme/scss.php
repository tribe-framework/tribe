<?php
include_once ('../init.php');
header("Content-Type: text/css; charset=utf-8");
$sass = new Sass();
$sass->setStyle(Sass::STYLE_COMPRESSED);
$sass->setEmbed(true);
$css = $sass->compileFile(THEME_PATH.'/scss/bootstrap.scss');
echo $css;
?>