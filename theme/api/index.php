<?php
//tribe init
include_once __DIR__ . '/../../_init.php';
include_once __DIR__ . '/../_init.php';

/* ANTHING THAT PRINTS JSON */
header('Content-Type: application/json');
$or = array('tribe_api'=>'hello, world');
echo json_encode($or);