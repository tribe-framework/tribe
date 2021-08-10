<?php
namespace Wildfire;
$dash = new Core\Dash();
$sql = new Core\MySQL();
$q = $sql->executeSQL("SELECT `id` FROM `data` WHERE `content`->'$.type'='user'");
if (!$q[0]['id']) {
    $usr = array();
    $usr['type'] = 'user';
    $usr['role_slug'] = 'admin';
    $usr['email'] = $_ENV['CONTACT_EMAIL'];
    $usr['password'] = md5($_ENV['DB_PASS']);
    $usr['user_id'] = $dash->get_unique_user_id();
    $dash->push_content($usr);
}
?>