<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$domains = json_decode(file_get_contents('domains.json'), true);

function scanEntries($scanfile, $domainlist) {
    $rows = array_map('str_getcsv', file($scanfile));

    //Get the first row that is the HEADER row.
    $header_row = array_shift($rows);
    //This array holds the final response.
    $entries    = [];
    foreach($rows as $row) {
        if(!empty($row)){
            if (count($header_row) === count($row))
                $entries[] = array_combine($header_row, $row);
        }
    }

    $exclude = [
        'general',
        'uncategorized',
        'other',
    ];
    $toplevelonly = [
        'freshchat.com',
        'liveperson.com',
        'boldchat.com',
        'cbox.ws',
        'srtrak.com',
    ];
    foreach($entries as $entry) {
        if (empty($entry['Category']) || in_array(strtolower($entry['Category']), $exclude)) continue;
        $domainlist[$entry['Site Name']] = $entry['Category'];
    }

    return $domainlist;
}

$files = glob(__DIR__ .'/../securlylogs/*.csv');

foreach($files as $file) {
    $domains = scanEntries($file, $domains);
}

$domaininfo = [];
foreach($domains as $url => $category) {
    $info = [];
    $info['url'] = $url;
    $parse = get_domain($url);
    $info['topdomain'] = $parse;
    $info['category'] = $category;
    $domaininfo[] = $info;
}

$topdomains = [];
foreach($domaininfo as $info) {
    $topdomains[$info['topdomain']]['count'] = ($topdomains[$info['topdomain']]['count'] ?? 0) + 1;
    $topdomains[$info['topdomain']]['categories'][$info['category']] = ($topdomains[$info['topdomain']]['categories'][$info['category']] ?? 0) + 1;
}

$filtereddomains = [];
foreach($topdomains as $domain => $info) {
    if ($info['count'] > 7)
        $filtereddomains[$domain] = $info;
}

file_put_contents('domains.json', json_encode($domains));

echo '<h2>' . count($filtereddomains) . ' Domains</h2>';
echo '<pre>';
print_r($filtereddomains);

$groupeddomains = [];
foreach($filtereddomains as $domain => $info) {
    if (count($info['categories']) == 1) {
        $categories = array_values(array_flip($info['categories']));
        $groupeddomains[$domain] = $categories[0];
    }
}

echo '<h2>' . count($groupeddomains) . ' Domains</h2>';
echo '<pre>';
print_r($groupeddomains);

//file_put_contents('groupeddomains.json', json_encode($domains));

function get_domain($host){
  $myhost = strtolower(trim($host));
  $count = substr_count($myhost, '.');
  if($count === 2){
    if(strlen(explode('.', $myhost)[1]) > 3) $myhost = explode('.', $myhost, 2)[1];
  } else if($count > 2){
    $myhost = get_domain(explode('.', $myhost, 2)[1]);
  }
  return $myhost;
}
