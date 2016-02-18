#!/usr/bin/env php
<?php

$algolia_key = getenv('algolia_key');

if (empty($algolia_key)) {
  echo "No Algolia key. Please export one.\n";
  exit;
}

require __DIR__ . '/vendor/autoload.php';

$client = new \AlgoliaSearch\Client('A644RMPSD6', $algolia_key);
$index = $client->initIndex('prod_drupal_modules');


$url = 'https://www.drupal.org/api-d7/node.json?type=project_module&status=1&field_project_type=full&limit=50';

$i = 0;
$max_pages = 800;

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
      'project_type' => $node->field_project_type,
      'project_machine_name' => $node->field_project_machine_name,
      'download_count' => $node->field_download_count,
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

