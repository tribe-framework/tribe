<?php
require __DIR__ . '/_init.php';
$uploads = new \Tribe\Uploads;

/* New code that handles file uploading */
if ($_FILES['file']) {
	header('Content-type: application/json; charset=utf-8');
	$handle = new \Verot\Upload\Upload($_FILES['file']);

	if ($handle->uploaded) {
	  
	  $file = array();
	  $uploader_path = $uploads->getUploaderPath();
	  $file_extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
	  $file['name'] = pathinfo($_FILES['file']['name'], PATHINFO_FILENAME).'_'.uniqid();
	  $file['url'] = $uploader_path['upload_url'].'/'.$file['name'].'.'.$file_extension;

	  if (!in_array(strtolower($file_extension), explode(',', $_ENV['ALLOWED_FILE_EXTENSIONS_IN_UPLOADS_FOLDER']))) {
	  	echo json_encode(array('status'=>'error', 'error_message'=>'File format not supported by server.'));
	  }
	  else {

		  $handle->file_new_name_body = $file['name'];
		  $handle->process($uploader_path['upload_dir']);
		  
		  if ($handle->processed) {
		    
		    echo json_encode(array('status'=>'success', 'file'=>$file));
		    $handle->clean();

		  } else {
		    echo json_encode(array('status'=>'error', 'error_message'=>$handle->error));
		  }
	  }
	}
}

/* Old code that handles S3 CDN redirection for uploads folder */
else if ($_ENV['S3_UPLOADS_BUCKET_CDN_URL'] ?? false) {
	$url = $_ENV['S3_UPLOADS_BUCKET_CDN_URL'].'/'.explode('/uploads/', $_SERVER['REQUEST_URI'])[1];
	$cnt = file_get_contents($url, false, stream_context_create(array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false))));
	$mime_type = getMimeType($cnt, 'str');  //get the mime-type of the content in $cnt

	// set the header and outputs the content
	header('Content-Type: '. $mime_type);
	echo $cnt;
	exit;
}

else 'File not found.';

function linux_command($cmd) {
	ob_start();
	passthru($cmd . ' > /dev/null 2>&1 &');
	$tml = ob_get_contents();
	ob_end_clean();
	return $tml;
}

function getMimeType($r, $t='file') {
	//Returns the Mime Type of a file or a string content - from: https://coursesweb.net/
	// $r = the resource: Path to the file; Or the String content
	// $t = type of the resource, needed to be specified as "str" if $r is a string-content
	$finfo = new finfo(FILEINFO_MIME_TYPE);
	return ($t =='str') ? $finfo->buffer($r) : $finfo->file($r);
}