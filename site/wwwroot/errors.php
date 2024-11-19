<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST,GET,OPTIONS');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');

$json_params = file_get_contents("php://input");

$date = new DateTime();
$date = $date->format('Y-m-d:H:i:s O');
error_log('[' . $date . '] ' . $json_params. PHP_EOL, 3, "../log/errors.log");
