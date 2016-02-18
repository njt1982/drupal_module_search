#!/usr/bin/env php
<?php

if (!array_key_exists('algolia_key', $_ENV)) {
  echo "No Algolia key. Please export one.\n";
  exit;
}

require __DIR__ . '/vendor/autoload.php';

$client = new \AlgoliaSearch\Client('A644RMPSD6', $_ENV['api_key']);
$index = $client->initIndex('prod_drupal_modules');


$url = 'https://www.drupal.org/api-d7/node.json?type=project_module&status=1&field_project_type=full&limit=20';

$i = 0;
$max_pages = 10;

do {
  echo "Processing: {$url}... ";
  $data = json_decode(file_get_contents($url));
  $batch = [];
  foreach ($data->list as $node) {
    $batch[] = [
      'objectID' => $node->nid,
      'title' => $node->title,
      'body' => strip_tags($node->body->value),
      'url' => $node->url,
    ];
  }
  $index->addObjects($batch);

  $url = (++$i < $max_pages) ? $data->next : FALSE;

  // Hack to fix https://www.drupal.org/node/2670512#comment-10867800
  if ($url) {
    if (strpos($url, 'node?') !== FALSE) {
      $url = str_replace('node?', 'node.json?', $url);
    }
  }

  echo "Done!\n";
} while($url);

/*

$some_projects = [];

$counts = [];
foreach ($xml as $project) {
  $simple = json_decode(json_encode($project));
  $is_sandbox = (stripos($simple->link, 'drupal.org/sandbox') !== FALSE) ? 'sandbox' : 'notsandbox';

  $key = "{$simple->type}--{$is_sandbox}--{$simple->project_status}";

  if (!array_key_exists($key, $counts)) $counts[$key] = 0;
  $counts[$key]++;

  if ($key == 'project_module--notsandbox--published' && count($some_projects) < 20) {
    $some_projects[] = $simple;
  }
}
ksort($counts);
print_r($counts);

print_r($some_projects);
*/
