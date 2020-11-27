<?php
function get_cover_media_html ($postdata, $css_class='') {
	$op='';
	if (is_array($postdata['cover_media'])) {
		if (count($postdata['cover_media'])==1)
			$op.='<img src="'.$postdata['cover_media'][0].'" class="w-100 '.$css_class.'">';
		else {
			$op.='<div class="w-100 '.$css_class.'" data-flickity=\'{"imagesLoaded": true, "percentPosition": false, "adaptiveHeight": true, "setGallerySize": true}\'>';
			foreach ($postdata['cover_media'] as $cover_media) {
				if ($cover_media)
					$op.='<img src="'.$cover_media.'">';
			}
			$op.='</div>';
		}
	}
	else
	  $op.='<img src="'.$postdata['cover_media'].'" class="w-100 '.$css_class.'">';
	
	return $op;
}
?>