<?php
include_once('_init.php');
$sql = new \Tribe\MySQL();
$core = new \Tribe\Core();

$q = $sql->executeSQL("SELECT `id` FROM `data` WHERE `type`='user'");
if (!$q[0]['id']) {
    $usr = array();
    $usr['type'] = 'user';
    $usr['role_slug'] = 'admin';
    $usr['email'] = $_ENV['CONTACT_EMAIL'];
    $usr['password'] = $_ENV['DB_PASS'];
    $usr['user_id'] = $core->getUniqueUserID();
    $core->pushObject($usr);
}
?>