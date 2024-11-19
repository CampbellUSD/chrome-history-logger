<?php
if ($_GET['key'] != $_ENV['CRON']) {
  die('Forbidden');
}

echo '<pre>';

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';

$scopes = array(
    'https://www.googleapis.com/auth/admin.directory.user'
);

$client = new Google\Client();
$client->useApplicationDefaultCredentials();
$client->setSubject($_ENV['GOOGLE_USER']);
$client->setScopes($scopes);

$dir = new Google\Service\Directory($client);

function csvToArray($path) {
  $rows = array_map('str_getcsv', file($path));
  $header_row = array_shift($rows);
  $entries = [];
  foreach($rows as $row) {
      if(!empty($row)){
          if (count($header_row) === count($row))
              $entries[] = array_combine($header_row, $row);
      }
  }
  return $entries;
}

$year = intval(date('y'));
$schoolyradj = 0;
if (intval(date('n')) > 6) $schoolyradj = 1;
$termyear = $year + $schoolyradj + 9; // 9 for some arbitrary powerschool reason

$sharepath = "/share/powerschoolftp/";

$entries = csvToArray($sharepath."chromebook_history_student.csv");
$studentIDs = (object) [];
foreach ($entries as $entry) {
  $studentIDs->{intval($entry['Student_id'])} = intval($entry['Student_number']);
}
$entries = csvToArray($sharepath."chromebook_history_cc.csv");
$studentSections = (object) [];
foreach ($entries as $entry) {
  $studenttermyear = intval(substr($entry['Term_id'], 0, 2));
  if (!empty($studentIDs->{$entry['Student_id']}) && $studenttermyear >= $termyear) {
    $studentNum = intval($studentIDs->{$entry['Student_id']});
    $studentSections->{$studentNum}[] = intval($entry['Section_id']);
  }
}
//print_r($studentSections);


function getAllUsers($dir) {
  $pageToken = "";
  $result = [];

  do {
    $page = $dir->users->listUsers(array(
      'domain' => 'campbellusd.org',
      'maxResults' => 500,
      'projection' => 'full',
      'query' => 'orgUnitPath=/Students/Automated',
      'pageToken' => $pageToken
    ));
    $result = array_merge($result,$page->users);
    $pageToken = $page->nextPageToken;
  } while ($pageToken);

  return $result;
}

$googleUserInfo = getAllUsers($dir);
$userInfoByGID = [];
//print_r($googleUserInfo);

foreach ($googleUserInfo as $user) {
  //if (empty(intval($user->customSchemas['Student_Information_System']['Student_Number'] ?? 0))) continue;
  $ous = explode('/', $user->orgUnitPath);

  if (!empty($studentSections->{intval($user->customSchemas['Student_Information_System']['Student_Number'] ?? 0)}))
    $userInfoByGID[$user->id]['sectionIDs'] = $studentSections->{intval($user->customSchemas['Student_Information_System']['Student_Number'] ?? 0)};
  else
    $userInfoByGID[$user->id]['sectionIDs'] = [];
  $userInfoByGID[$user->id]['school'] = $ous[4];
  $userInfoByGID[$user->id]['grade'] = $ous[5];
  //$userInfoByGID[$user->id]['sid'] = $user->externalIds[0]['value'];
  //$userInfoByGID[$user->id]['mail'] = $user->primaryEmail;

  print_r($userInfoByGID[$user->id]);
}

file_put_contents("../cache/userinfo.json", json_encode($userInfoByGID));

