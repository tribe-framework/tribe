<?php
require __DIR__ . '/_init.php';

use alsvanzelf\jsonapi\CollectionDocument;
use alsvanzelf\jsonapi\ResourceDocument;

$config = new \Tribe\Config;
$core = new \Tribe\Core;
$api = new \Tribe\API;
$auth = new \Tribe\Auth;

$url_parts = array_filter(explode('/', $_SERVER['REQUEST_URI']));
$type = $url_parts[2] ?? false;
$id = (int) $url_parts[3] ?? false;

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
	if ($id) {
		$core->deleteObject($id);
	    $document = new ResourceDocument();
		$document->sendResponse();
	}
}

else if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $object = $api->requestBody;
	$object = array_merge($core->getObject($object['data']['id']), $object['data'], $object['data']['attributes']['modules']);
	unset($object['attributes']);
	
	$object = $core->getObject($core->pushObject($object));

    $document = new ResourceDocument($type=$type, $object['id']);
    $document->add('modules', $object);
	$document->sendResponse();
}

else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$object = $api->requestBody;
	$object = array_merge($object['data'], $object['data']['attributes']['modules']);
	unset($object['attributes']);

	if ($object['type'] == 'user')
		$object['user_id'] = $auth->getUniqueUserID();

	$object = $core->getObject($core->pushObject($object));

    $document = new ResourceDocument($type=$type, $object['id']);
    $document->add('modules', $object);
	$document->sendResponse();
}

else {

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

	else if (($type ?? false) && !($id ?? false)) {
		$show_public_objects_only = ($_GET['show_public_objects_only'] ?? false);
		
		if ($ids = $core->getIDs(array('type'=>$type), '=', 'AND', 'id', 'DESC', 10, $show_public_objects_only)) {
			$objects = $core->getObjects($ids);
			$i = 0;
			foreach ($objects as $object) {
				$documents[$i] = new ResourceDocument($type=$type, $object['id']);
				$documents[$i]->add('modules', $object);
				$i++;
			}
			$document = CollectionDocument::fromResources(...$documents);
			$document->sendResponse();
		} else {
			$document = new ResourceDocument();
			$document->sendResponse();
		}
	}

	else if (($type ?? false) && ($id ?? false)) {
		if ($object = $core->getObject($id)) {
			$document = new ResourceDocument($type=$type, $object['id']);
			$document->add('modules', $object);
			$document->sendResponse();
		} else {
			$document = new ResourceDocument();
			$document->sendResponse();
		}
	}

	else {
			$document = new ResourceDocument();
			$document->sendResponse();
	}
}