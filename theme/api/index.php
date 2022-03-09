<?php
//tribe init
include_once __DIR__ . '/../../_init.php';
include_once __DIR__ . '/../_init.php';

use alsvanzelf\jsonapi\CollectionDocument;
use alsvanzelf\jsonapi\ResourceDocument;

if ($type == 'types_json') {
	$document = new ResourceDocument($type='types_json', $id=0);
	$document->add('schema', $dash->getTypes());
	$document->sendResponse();
}
else if ($type && $slug) {
	$object=$dash->getObject($slug);
	$document = new ResourceDocument($type=$object['type'], $id=$object['id']);
	foreach ($object as $key => $value) {
		if ($key!='id' && $key!='type')
			$document->add($key, $value);
	}

	$document->sendResponse();
}
else if ($type) {
	$i=$j=0;

	$ids = $dash->get_ids(array('type'=>$type), '=', 'AND', 'id', 'DESC', 10);

	foreach ($ids as $idr) {
		$slug = $idr['id'];
		$object=$dash->getObject($slug);
		$arr[$i] = new ResourceDocument($type=$object['type'], $id=$object['id']);

		foreach ($object as $key => $value) {
			if ($key!='id' && $key!='type')
				$arr[$i]->add($key, $value);
		}

		$i++;
	}

	$document = CollectionDocument::fromResources(...$arr);
	$document->sendResponse();
}