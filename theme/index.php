<?php include_once (THEME_PATH.'/header.php'); ?>

<?php $type='page'; $postids=$dash::get_all_ids($type); ?>

<div class="row-cols-1 row-cols-md-3 mt-4 ml-auto">

<?php 
foreach ($postids as $postarr):
	$postdata=$dash::get_content($postarr['id']);
	if ($postdata['content_privacy']=='public'):
?>

<div class="col mb-4">
  <div class="card">
    <?php echo ($postdata['cover_media']?get_cover_media_html($postdata, 'card-img-top'):''); ?>
    <div class="card-body">
      <h5 class="card-title"><a href="<?php echo BASE_URL.'/'.$type.'/'.$postdata['slug']; ?>"><?php echo $postdata['title']; ?></a></h5>
      <p class="card-text"><?php echo $postdata['short_description']; ?></p>
    </div>
    <div class="card-footer"><a class="text-secondary font-italic mr-0 ml-auto" href="<?php echo BASE_URL.'/'.$type.'/'.$postdata['slug']; ?>">... read more</a></div>
  </div>
</div>

<?php
	endif;
endforeach;
?>

</div>

<?php include_once (THEME_PATH.'/footer.php'); ?>