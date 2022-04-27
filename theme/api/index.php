<?php
/*

Based on JSON API v1.1 (https://jsonapi.org/format/1.1), URL params:
sort=title for ascending or sort=-title for descending
page[offset] and page['limit'] for pagination

*/
include_once __DIR__ . '/../../_init.php';
$dash = new \Wildfire\Core\Dash();

use alsvanzelf\jsonapi\CollectionDocument;
use alsvanzelf\jsonapi\ResourceDocument;

if ($type == 'types_json') {
	$document = new ResourceDocument('types_json', ($slug ?? 0));
	if ($slug)
		$document->add('schema', $dash->getTypes()[$slug]);
	else
		$document->add('schema', $dash->getTypes());
	$document->sendResponse();
}

else if ($type && $slug) {
	$object=$dash->getObject(array('type'=>$type, 'slug'=>$slug));
	$document = new ResourceDocument($object['type'], $object['slug']);
	$document->add('modules', $object);
	$document->sendResponse();
}

else if ($type) {
	$page = $_GET['page'] ?? NULL;
	$limit = ($page['offset'] ?? 0) . ', ' . ($page['limit'] ?? 10);

	$sort_by = $_GET['sort'] ?? 'id';
	if (substr($sort_by, 0, 1)=='-') {
		$sort_order = 'DESC';
		$sort_by = substr($sort_by, 1, strlen($sort_by));
	}
	else {
		$sort_order = 'ASC';
	}

	if ($sort_order=='ASC')
		$multisort_order_tag = SORT_ASC;
	else
		$multisort_order_tag = SORT_DESC;

	$objects = $dash->getObjects( $dash->get_ids(array('type'=>$type), '=', 'AND', $sort_by, $sort_order, $limit) );
	
	if ($objects) {
		array_multisort(array_column($objects, $sort_by), $multisort_order_tag, $objects);

		$i=0;
		foreach ($objects as $object) {
			$arr[$i] = new ResourceDocument($object['type'], $object['slug']);
			$arr[$i]->add('modules', $object);
			$i++;
		}

		$document = CollectionDocument::fromResources(...$arr);
	}

	else {
		//NULL data
		$document = new ResourceDocument();
	}

	$document->sendResponse();
}
else {
	//NULL data
	$document = new ResourceDocument();
	$document->sendResponse();
}