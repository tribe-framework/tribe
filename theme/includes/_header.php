<?php

use Wildfire\Core\Theme;
$theme = new Theme();

?>

<!doctype html>
<html lang="<?=$types['webapp']['lang']?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

	<title><?=$meta_title?></title>
	<meta name="description" content="<?=$meta_description?>">
	<meta property="og:title" content="<?=$meta_title?>">
	<meta property="og:description" content="<?=$meta_description?>">
	<meta property="og:image" content="<?=$meta_image_url?>">

	<link href="/sass?file=bootstrap" rel="stylesheet">
	<link href="/plugins/fontawesome/css/all.min.css" rel="stylesheet">
	<link href="https://unpkg.com/flickity@2/dist/flickity.min.css" rel="stylesheet">
	<link href="<?=THEME_URL; ?>/css/custom.css" rel="stylesheet">
	<script>const THEME_URL = "<?=THEME_URL?>";</script>
</head>

<body>
<?=$theme->get_navbar_menu('main')?>
