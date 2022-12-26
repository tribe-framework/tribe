<?php
require __DIR__ . '/_init.php';
use alsvanzelf\jsonapi\ResourceDocument;
$config = new \Tribe\Config;
$core = new \Tribe\Core;

$url_parts = array_filter(explode('/', $_SERVER['REQUEST_URI']));
$type = $url_parts[2] ?? false;
$id = $url_parts[3] ?? false;
$object = $config->getTypes();

foreach ($object as $key => $value) {
	echo $core->getTypeObjectsCount($key);
}

$document = new ResourceDocument($type=$type, 0);
$document->add('modules', $object);
$document->addExtensionMember('meta', 'bar', 'baz');

$document->sendResponse();