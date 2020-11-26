<?php
include_once('config/vars.php');
$sql = new Wildfire\Core\MySQL();
$dash = new Wildfire\Core\Dash();
$types=$dash->get_types(ABSOLUTE_PATH.'/config/types.json');
header('Content-type: application/xml; charset=utf-8');
$or=array();
$xml = new SimpleXMLElement('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');
$or['url']=array('loc'=>BASE_URL, 'lastmod'=>'2020-06-20', 'priority'=>'1');
to_xml($xml, $or);
foreach ($types['webapp']['searchable_types'] as $tp) {
	$posts=$sql->executeSQL("SELECT `id`, `content`->>'$.slug' `slug`, `updated_on` FROM `data` WHERE `content`->'$.content_privacy'='public' && `content`->'$.type'='$tp' ORDER BY `id` DESC");
	foreach ($posts as $post) {
		$or['url']=array('loc'=>BASE_URL.'/'.$tp.'/'.$post['slug'], 'lastmod'=>date('Y-m-d', $post['updated_on']), 'priority'=>'0.7');
		to_xml($xml, $or);
	}
}
print $xml->asXML();
function to_xml(SimpleXMLElement $object, array $data) {
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $new_object = $object->addChild($key);
            to_xml($new_object, $value);
        } else {
            if (is_int($key))
                $key = "key_$key";
            $object->addChild($key, $value);
        }
    }
}
?>