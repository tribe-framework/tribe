<?php
include_once(__DIR__.'/vars.php');
if (file_exists(THEME_PATH.'/config/vars.php'))
	include_once(THEME_PATH.'/config/vars.php');

$folders_to_backup=array(ABSOLUTE_PATH.'/uploads', ABSOLUTE_PATH.'/themes');
foreach ($folders_to_backup as $folder)
	linux_command('s3cmd sync -r --delete-removed --host="'.S3_BKUP_HOST_BASE.'" --access_key="'.S3_BKUP_ACCESS_KEY.'" --secret_key="'.S3_BKUP_SECRET_KEY.'" --host-bucket="'.S3_BKUP_HOST_BUCKET.'" '.$folder.' s3://'.S3_BKUP_FOLDER_NAME.'/');

$backupfile='backup-'.DB_NAME.'.sql.gz';
linux_command('mysqldump --no-tablespaces -u'.DB_USER.' -p'.DB_PASS.' '.DB_NAME.' | gzip > /var/tmp/'.$backupfile);
linux_command('s3cmd --host="'.S3_BKUP_HOST_BASE.'" --access_key="'.S3_BKUP_ACCESS_KEY.'" --secret_key="'.S3_BKUP_SECRET_KEY.'" --host-bucket="'.S3_BKUP_HOST_BUCKET.'" put /var/tmp/'.$backupfile.' s3://'.S3_BKUP_FOLDER_NAME.'/');
linux_command('rm /var/tmp/'.$backupfile);

function linux_command ($cmd) {
	ob_start();
	passthru($cmd);
	$tml = ob_get_contents();
	ob_end_clean();
	return $tml;
}
?>