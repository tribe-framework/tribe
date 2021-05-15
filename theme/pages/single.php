<?php include_once __DIR__ . '/_header.php'?>

<div class="container d-flex" style="min-height: 50vh;">

  <div class="row align-items-center"><div class="mx-auto col-12">

	<?php if ($postdata['content_privacy'] != 'draft'): ?>

	<?php echo Functions::get_cover_media_html($postdata); ?>

	<?php echo (trim($postdata['body']) ? '<div class="mx-auto post-body w-100">' . $postdata['body'] . '</div>' : ''); ?>

	<?php endif;?>

	</div></div>
</div>
<?php include_once __DIR__ . '/_footer.php'?>