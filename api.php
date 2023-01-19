<?php
require __DIR__ . '/_init.php';

use alsvanzelf\jsonapi\CollectionDocument;
use alsvanzelf\jsonapi\ResourceDocument;

$config = new \Tribe\Config;
$core = new \Tribe\Core;
$api = new \Tribe\API;
$sql = new \Tribe\MySQL;
$auth = new \Tribe\Auth;

$url_parts = explode('/', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$type = (string) ($url_parts[2] ?? '');
$id = (int) ($url_parts[3] ?? 0);



if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
	if ($id) {
		$core->deleteObject($id);
	    $document = new ResourceDocument();
		$document->sendResponse();
	}
	else {
			$document = new ResourceDocument();
			$document->sendResponse();
	}
}

else if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $object = $api->requestBody;
	$object = array_merge($core->getObject($object['data']['id']), $object['data'], $object['data']['attributes']['modules']);
	unset($object['attributes']);
	
	$object = $core->getObject($core->pushObject($object));

    $document = new ResourceDocument($type, $object['id']);
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

    $document = new ResourceDocument($type, $object['id']);
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

		$document = new ResourceDocument($type, 0);
		$document->add('modules', $object);
		$document->sendResponse();
	}

	else if (($type ?? false) && !($id ?? false)) {
		$show_public_objects_only = ($_GET['show_public_objects_only'] ?? false);

		//FILTERING
		$limit = "0, 25";
		if (!($_GET['page']['offset'] ?? false))
			$_GET['page']['offset'] = 0;
		if (!($_GET['page']['limit'] ?? false))
			$_GET['page']['limit'] = 25;

		if (($_GET['page']['limit'] ?? false) !== null && ($_GET['page']['offset'] ?? false) !== null)
			$limit = "{$_GET['page']['offset']}, {$_GET['page']['limit']}";
		else if (($_GET['page']['limit'] ?? false) !== null)
			$limit = $_GET['page']['limit'];

		//SORTING
		if ($_GET['modules'] ?? false) {

			foreach ($_GET['modules'] as $key=>$value) {
				$search_query[$key][] = $value;
			}
				
			$query_lines = array();
			$search_keys = array_keys($search_query);
			
			foreach ($search_keys as $key) {
				$search_query[$key] = array_unique($search_query[$key]);
				$query_lines[] = " ( LOWER(`content`->>'$.".$key."') LIKE '%".implode("%' OR LOWER(`content`->>'$.".$key."') LIKE '%", array_map('strtolower', array_values($search_query[$key])))."%' ) ";
			}

			$query = "SELECT `id` FROM `data` WHERE `type`='".$type."' AND ".implode(" AND ", $query_lines)." ORDER BY `id` DESC LIMIT ".$limit;

			$ids = $sql->executeSQL($query);;

			if ($ids) {
				$objects = $core->getObjects($ids);
				$i = 0;
				foreach ($objects as $object) {
					$documents[$i] = new ResourceDocument($type, $object['id']);
					$documents[$i]->add('modules', $object);
					$i++;
				}
				$document = CollectionDocument::fromResources(...$documents);
				$document->sendResponse();
			}
			else {
				$documents = [];
				$document = CollectionDocument::fromResources(...$documents);
				$document->sendResponse();
			}

		}

		else if ($ids = $core->getIDs(array('type'=>$type), '=', 'AND', 'id', 'DESC', $limit, $show_public_objects_only)) {
			$objects = $core->getObjects($ids);
			$i = 0;
			foreach ($objects as $object) {
				$documents[$i] = new ResourceDocument($type, $object['id']);
				$documents[$i]->add('modules', $object);
				$i++;
			}
			$document = CollectionDocument::fromResources(...$documents);
			$document->sendResponse();
		} 

		else {
			$document = new ResourceDocument();
			$document->sendResponse();
		}
	}

	else if (($type ?? false) && ($id ?? false)) {
		if ($object = $core->getObject($id)) {
			$document = new ResourceDocument($type, $object['id']);
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