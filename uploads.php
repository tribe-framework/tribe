<?php
//composer autoload
require __DIR__ . '/vendor/autoload.php';

//dotenv for loading variables in tribe.var.env as $_ENV
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '.env');
$dotenv->load();

//php vars file
include_once __DIR__ . '/config/config.php';

if ($_ENV['S3_UPLOADS_BUCKET_CDN_URL'] ?? false) {
	$url = $_ENV['S3_UPLOADS_BUCKET_CDN_URL'].'/'.explode('/uploads/', $_SERVER['REQUEST_URI'])[1];
	$cnt = file_get_contents($url, false, stream_context_create(array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false))));
	$mime_type = getMimeType($cnt, 'str');  //get the mime-type of the content in $cnt

	// set the header and outputs the content
	header('Content-Type: '. $mime_type);
	echo $cnt;
	exit;
}
else 'File not found.';



//SET PERMISSION PUBLIC

//linux_command('sudo s3cmd setacl s3://' . $_ENV['S3_UPLOADS_BUCKET_NAME'] . ' --acl-public -r --host="' . $_ENV['S3_UPLOADS_HOST_BASE'] . '" --access_key="' . $_ENV['S3_UPLOADS_ACCESS_KEY'] . '" --secret_key="' . $_ENV['S3_UPLOADS_SECRET_KEY'] . '" --host-bucket="' . $_ENV['S3_UPLOADS_HOST_BUCKET'] . '"');

//GET FOLDER BACK FROM S3 TO LOCAL

//linux_command('sudo s3cmd get s3://<s3-folder-path> <local-folder-path> -r --host="s3.wasabisys.com" --access_key="" --secret_key="" --host-bucket="%(bucket)s.s3.wasabisys.com"');



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