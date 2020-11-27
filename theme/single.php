<?php include_once (THEME_PATH.'/header.php'); ?>

<?php if ($postdata['content_privacy']!='draft'): ?>

<div class="jumbotron text-white rounded-0 bg-dark p-0">
<?php echo get_cover_media_html ($postdata); ?>
</div>

<div class="col-md-9 px-2 m-auto mt-0"><h1 class="display-4"><?php echo $postdata['title']; ?></h1></div>

<div class="col-md-9 px-2 m-auto mt-0">
<?php echo ($postdata['publishing_date']?'<p class="text-secondary my-4">'.date('d, M Y', strtotime($postdata['publishing_date'])).'</p>':''); ?>

<div class="my-4 post-body"><?php echo $postdata['body']; ?></div>

</div>

<?php endif; ?>

<?php include_once (THEME_PATH.'/footer.php'); ?>