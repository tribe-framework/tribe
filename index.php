<?php
include_once 'tribe.init.php';

isset($types['webapp']['lang'])?:$types['webapp']['lang']='en';

if (isset($_GET['ext'])) { //for theme
    $ext=explode('/', $_GET['ext']);

    if (count($ext)) {
        $type=$dash->do_unslugify($ext[0]);
    }

    if (count($ext)>1) {
        $slug=$dash->do_unslugify($ext[1]);
    }
} elseif (isset($_GET['type'])) { //for dashboard
    $type=$dash->do_unslugify($_GET['type']);
}

if (file_exists(THEME_PATH.'/functions.php'))
	include_once(THEME_PATH.'/functions.php');

if (($type ?? '')=='search'):
	if ($slug && !$_GET['q']) {
		$_GET['q']=$slug;
	}

	if (!file_exists(THEME_PATH.'/search.php')) {
		echo 'Include a search.php file in your theme folder.';
		return false;
	}

	include_once (THEME_PATH.'/search.php');
elseif (isset($type) && isset($slug)):
	$typedata=$types[$type];
	$postdata=$dash->get_content(array('type'=>$type, 'slug'=>$slug));

	if ($postdata) {
		$postdata_modified=$postdata;

		$headmeta_title=$types[$type]['headmeta_title'];
		$headmeta_description=$types[$type]['headmeta_description'];
		$headmeta_image_url=$types[$type]['headmeta_image_url'];

		$append_phrase='';
		if ($types[$type]['headmeta_title_append']) {
			foreach ($types[$type]['headmeta_title_append'] as $appendit) {
				$key=$appendit['type']; $value=$appendit['slug'];
				$append_phrase.=' '.$types[$type]['headmeta_title_glue'].' '.$types[$key][$value];
			}
		}
		$prepend_phrase='';
		if ($types[$type]['headmeta_title_prepend']) {
			foreach ($types[$type]['headmeta_title_prepend'] as $prependit) {
				$key=$prependit['type']; $value=$prependit['slug'];
				$prepend_phrase.=$types[$key][$value].' '.$types[$type]['headmeta_title_glue'].' ';
			}
		}

		$postdata_modified[$headmeta_title]=$prepend_phrase.$postdata[$headmeta_title].$append_phrase;

		$postdata_modified[$headmeta_description]=trim(strip_tags($postdata_modified[$headmeta_description]));
		if (strlen($postdata_modified[$headmeta_description]) > 160)
			$postdata_modified[$headmeta_description]=substr($postdata_modified[$headmeta_description], 0, 154).' [...]';

		if (!($meta_title=($postdata_modified[$headmeta_title]??null))) {
			if (!($meta_title=($types['webapp']['headmeta_title']??null)))
				$meta_title='';
		}

		if (!($meta_description=($postdata_modified[$headmeta_description]??null))) {
			if (!($meta_description=($types['webapp']['headmeta_description']??null)))
				$meta_description='';
		}

		if (!($meta_image_url=($postdata_modified[$headmeta_image_url][0]??null))) {
			if (!($meta_image_url=($postdata_modified[$headmeta_image_url]??null))) {
				if (!($meta_image_url=($types['webapp']['headmeta_image_url']??null)))
					$meta_image_url='';
			}
		}

		//single-ID for specific post, or a single-type template for all posts in that type (single-type is different from archive-type)
		if (file_exists(THEME_PATH.'/single-'.$postdata['id'].'.php'))
			include_once (THEME_PATH.'/single-'.$postdata['id'].'.php');
		elseif (file_exists(THEME_PATH.'/single-'.$type.'.php'))
			include_once (THEME_PATH.'/single-'.$type.'.php');
		elseif (file_exists(THEME_PATH.'/single.php'))
			include_once (THEME_PATH.'/single.php');
		else
			include_once (THEME_PATH.'/index.php');
	} else {
		include_once (THEME_PATH.'/404.php');
	}
elseif (isset($type)):
	$typedata=$types[$type];

	if ($typedata) {
		$postids=$dash->get_all_ids($type);

		if (!($meta_title=($types[$type]['meta_title']??null))) {
			if (!($meta_title=($types['webapp']['headmeta_title']??null)))
				$meta_title='';
		}

		if (!($meta_description=($types[$type]['meta_description']??null))) {
			if (!($meta_description=($types['webapp']['headmeta_description']??null)))
				$meta_description='';
		}

		if (!($meta_image_url=($types[$type]['meta_image_url']??null))) {
			if (!($meta_image_url=($types['webapp']['headmeta_image_url']??null)))
				$meta_image_url='';
		}

		//archive-type is template for how the type is listed, not to be confused with single-type
		if (file_exists(THEME_PATH.'/archive-'.$type.'.php'))
			include_once (THEME_PATH.'/archive-'.$type.'.php');
		else if (file_exists(THEME_PATH.'/archive.php'))
			include_once (THEME_PATH.'/archive.php');
		else
			include_once (THEME_PATH.'/index.php');
	}
	else
		include_once (THEME_PATH.'/404.php');
else:
	$meta_title=($types['webapp']['headmeta_title']??'');
	$meta_description=($types['webapp']['headmeta_description']??'');
	$meta_image_url=($types['webapp']['headmeta_image_url']??'');

	include_once (THEME_PATH.'/index.php');
endif;
?>