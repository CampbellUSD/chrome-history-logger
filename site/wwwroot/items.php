<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST,GET,OPTIONS');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');

$date = new DateTime();
$date = $date->format('Y-m-d:H:i:s O');
error_log('[' . $date . '] ' . $_GET['id']. ' ' . $_GET['length']. PHP_EOL, 3, "../log/length.log");
