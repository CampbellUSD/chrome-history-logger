<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST,GET,OPTIONS');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');

ini_set('precision', 40);
ini_set('serialize_precision', 40);

$date = new DateTime();
$date = $date->format('Y-m-d:H:i:s O');

$userinfo = json_decode(file_get_contents('../cache/userinfo.json'), true);
$domains = json_decode(file_get_contents('domains.json'));

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

  if (empty($_GET['id'])) die();

  $latest = 0;
  if (file_exists("../cache/latest/".$_GET['id'].".txt"))
    $latest = file_get_contents("../cache/latest/".$_GET['id'].".txt");

  if (empty($latest))
    $latest = '0';

  echo $latest;

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

  function isValidJSON($str) {
     json_decode($str);
     return json_last_error() == JSON_ERROR_NONE;
  }

  $json_params = file_get_contents("php://input");

  if (strlen($json_params) > 0 && isValidJSON($json_params)) {
    $items = json_decode($json_params, true);
  }

  if (empty($items["id"])) die();
  if (!isset($userinfo[$items["id"]])) die();

  $data = [];
  foreach($items['items'] as $item) {
    $item['gid'] = $items["id"];
    $item['school'] = $userinfo[$item['gid']]['school'];
    $item['grade'] = $userinfo[$item['gid']]['grade'];
    $item['sectionIDs'] = $userinfo[$item['gid']]['sectionIDs'];

    $urlhost = parse_url($item['url'], PHP_URL_HOST);
    $domain = str_ireplace('www.', '', $urlhost);
    $item['category'] = $domains->{$domain} ?? null;
    if (empty($item['category'])) {
      $domain = preg_replace("/^([a-zA-Z0-9].*\.)?([a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z.]{2,})$/", '$2', $urlhost);
      $item['category'] = $domains->{$domain} ?? null;
    }
    if (is_null($item['category'])) $item['category'] = "";
    $data[] = $item;
  }

  $status = sendToSplunk($data);
  $status = json_decode($status);
  print_r($data);
  $date = new DateTime();
  $date = $date->format('Y-m-d:H:i:s O');
  if ($status->code !== 0)
    error_log('[' . $date . '] ' . $status->code . ' "' . $status->text . '"' . PHP_EOL, 3, "../log/splunkmessages.log");

  // if successfully sent to splunk, save to latest
  if ($status->code == 0) {
    $itemsnewfirst = array_reverse($items['items']);
    if (!empty($itemsnewfirst[0]['visitTime'])) {
      $visitTime = $itemsnewfirst[0]['visitTime'];
      file_put_contents('../cache/latest/'.$items["id"].'.txt', $visitTime);
    }
  }
}

function sendToSplunk($data) {
  $url = 'https://http-inputs-campbellusd.splunkcloud.com/services/collector/raw';
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type:application/json',
    'Authorization: Splunk ' . $_ENV['SPLUNK_AUTH']
  ]);
  $result = curl_exec($ch);
  curl_close($ch);
  return $result;
}
