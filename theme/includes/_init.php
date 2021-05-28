<?php
namespace Wildfire;

$dash = new Core\Dash();
$admin = new Core\Admin();
$sql = new Core\MySQL();
$auth = new Auth\Auth();

$types = $dash->getTypes();
$menus = $dash->getMenus();
$currentUser = $auth->getCurrentUser();

include_once __DIR__ . '/functions.php';
$functions = new Theme\Functions();