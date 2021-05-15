<?php
namespace Wildfire\Core;

$sql = new MySQL();
$dash = new Dash();
$admin = new Admin();
$theme = new Theme();

$types = $dash->getTypes();
$menus = $dash->getMenus();
$session_user = $dash->getSessionUser();

header('Content-Type: application/json');

$or = array();
$or = array('id' => $_GET['id'], 'foo' => $slug);

echo json_encode($or);