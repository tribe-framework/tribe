<?php
$dash = new \Wildfire\Core\Dash();
$admin = new \Wildfire\Core\Admin();
$sql = new \Wildfire\Core\MySQL;
$auth = new \Wildfire\Auth;

$types = $dash->getTypes();
$menus = $dash->getMenus();
$currentUser = $auth->getCurrentUser();

include_once __DIR__ . '/functions.php';
$functions = new Theme\Functions();
