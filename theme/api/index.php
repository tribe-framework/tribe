<?php include_once __DIR__ . '/../includes/_init.php';?>

<?php
/* ANTHING THAT PRINTS JSON */
header('Content-Type: application/json');
$or = array('tribe_api'=>'hello, world');
echo json_encode($or);