<?php
# things you can modify
define('ENV', 'prod'); //use 'dev' to print all errors
define('UPLOAD_FILE_TYPES', '/\.(zip|png|jpe?g|gif|pdf|doc|docx|xls|xlsx|mov|mp4|vtt)$/i');
date_default_timezone_set('Asia/Kolkata');

# do not modify beyond this line
define('THEME', $_ENV['WEB_URL']);
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS']);
define('DB_HOST', $_ENV['DB_HOST']);
define('BARE_URL', THEME);
define('ABSOLUTE_PATH', $_ENV['ABSOLUTE_PATH']);
define('CONTACT_EMAIL', $_ENV['CONTACT_EMAIL']);
define('WEBSITE_NAME', $_ENV['WEBSITE_NAME']);
define('CONTACT_NAME', $_ENV['CONTACT_NAME']);
define('S3_BKUP_HOST_BASE', $_ENV['S3_BKUP_HOST_BASE']);
define('S3_BKUP_HOST_BUCKET', $_ENV['S3_BKUP_HOST_BUCKET']);
define('S3_BKUP_ACCESS_KEY', $_ENV['S3_BKUP_ACCESS_KEY']);
define('S3_BKUP_SECRET_KEY', $_ENV['S3_BKUP_SECRET_KEY']);
define('S3_BKUP_FOLDER_NAME', BARE_URL);
define('BASE_URL', 'https://' . BARE_URL);
define('THEME_URL', BASE_URL.'/theme');
define('THEME_PATH', ABSOLUTE_PATH.'/theme');
?>