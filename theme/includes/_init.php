<?php
namespace Wildfire;

$dash = new Core\Dash();
$admin = new Core\Admin();
$sql = new Core\MySQL();

$types = $dash->getTypes();
$menus = $dash->getMenus();
$session_user = $dash->getSessionUser();

include_once __DIR__ . '/functions.php';
$functions = new Theme\Functions();