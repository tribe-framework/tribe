<?php
require __DIR__ . '/_init.php';
use alsvanzelf\jsonapi\ResourceDocument;
$config = new \Tribe\Config;
$core = new \Tribe\Core;

$url_parts = array_filter(explode('/', $_SERVER['REQUEST_URI']));
$type = $url_parts[2] ?? false;
$id = $url_parts[3] ?? false;

if ($type == 'webapp') {
	$object = $config->getTypes();

	if ( ($_GET['include'] ?? false) && in_array('total_objects', $_GET['include']) ) {
		foreach ($object as $key => $value) {
			$object[$key]['total_objects'] = $core->getTypeObjectsCount($key);
		}
	}

	$document = new ResourceDocument($type=$type, 0);
	$document->add('modules', $object);
	$document->sendResponse();
}
