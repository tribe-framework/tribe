<?php include_once __DIR__ . '/_header.php'?>

<div class="container d-flex" style="min-height: 50vh;">

  <div class="row align-items-center"><div class="mx-auto col-12">

		<h1>Single page layout</h1>
		<div class="col-12 replace_body_on_load single-post-body" data-id="<?=$postdata['id']?>">
			<?php print_r($postdata) ?>
		</div>

	</div></div>
</div>
<?php include_once __DIR__ . '/_footer.php'?>