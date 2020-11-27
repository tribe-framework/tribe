<!doctype html>
<html lang="<?php echo $types['webapp']['lang']; ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

	<title><?php echo $meta_title; ?></title>
	<meta name="description" content="<?php echo $meta_description; ?>">
	<meta property="og:title" content="<?php echo $meta_title; ?>">
	<meta property="og:description" content="<?php echo $meta_description; ?>">
	<meta property="og:image" content="<?php echo $meta_image_url; ?>">

	<link href="<?php echo THEME_URL; ?>/plugins/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="<?php echo THEME_URL; ?>/plugins/fontawesome/css/all.min.css" rel="stylesheet">
	<link href="<?php echo THEME_URL; ?>/css/custom.css" rel="stylesheet">
	<script type="text/javascript">var THEME_URL = "<?php echo THEME_URL; ?>";</script>
</head>

<body>
<?php echo $theme->get_navbar_menu('main'); ?>