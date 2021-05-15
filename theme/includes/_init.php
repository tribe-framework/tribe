<?php
namespace Wildfire\Core;

$dash = new Dash();
$admin = new Admin();
$theme = new Theme();
$sql = new MySQL();

$types = $dash->getTypes();
$menus = $dash->getMenus();
$session_user = $dash->getSessionUser();

include_once __DIR__ . '/functions.php';
$functions = new Functions();