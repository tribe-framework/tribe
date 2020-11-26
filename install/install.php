<?php
include_once 'xyz-install-path/xyz-domain-var/init.php';

//create first user
$usr=array();
$usr['type']='user';
$usr['role_slug']='admin';
$usr['email']='your_server_email';
$usr['password']='xyz-db-pass-var';
$usr['user_id']=$dash->get_unique_user_id();
$dash->push_content($usr);
?>