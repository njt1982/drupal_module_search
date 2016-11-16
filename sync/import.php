#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

try {
  $dotenv = new Dotenv\Dotenv(__DIR__);
  $dotenv->load();
  $dotenv->required(['ALGOLIA_KEY', 'ALGOLIA_APP_ID', 'ALGOLIA_INDEX'])->notEmpty();
}
catch(Dotenv\Exception\ValidationException $e) {
  echo $e->getMessage();
  exit;
}
catch(Dotenv\Exception\InvalidPathException $e) {
  echo $e->getMessage();
}


$algolia_key = getenv('ALGOLIA_KEY');
$algolia_app_id = getenv('ALGOLIA_APP_ID');
$algolia_index = getenv('ALGOLIA_INDEX');


$limit = getenv('limit') ?: 10;
echo "Importing at {$limit} per page.\n";


$algolia = new \AlgoliaSearch\Client($algolia_app_id, $algolia_key);
$index = $algolia->initIndex($algolia_index);


require __DIR__ . '/api.php';
$api = new DrupalApi();

$max_pages = 800;
$page = 0;
do {
  echo "Getting page: {$page} ";
  $data = $api->getProjects($page, $limit);

  $batch = [];
  foreach ($data->list as $node) {
    echo ".";
    $obj = [
      'objectID' => $node->nid,
      'title' => $node->title,
      'body' => strip_tags($node->body->value),
      'url' => $node->url,
      'project_type' => $node->field_project_type,
      'project_machine_name' => $node->field_project_machine_name,
      'download_count' => intval($node->field_download_count),
      'compatibility' => [],
    ];

    if (property_exists($node, 'taxonomy_vocabulary_44')) {
      if ($term = $api->getTerm($node->taxonomy_vocabulary_44->id)) {
        $obj += [
          'maintenance_status' => $term->name,
        ];
      }
    }

    if (property_exists($node, 'taxonomy_vocabulary_46')) {
      if ($term = $api->getTerm($node->taxonomy_vocabulary_46->id)) {
        $obj += [
          'development_status' => $term->name,
        ];
      }
    }

    if (property_exists($node, 'taxonomy_vocabulary_3')) {
      $obj += [
        'category' => [],
      ];
      foreach ($node->taxonomy_vocabulary_3 as $category) {
        if ($term = $api->getTerm($category->id)) {
          if (empty($term) || !property_exists($term, 'name')) {
            echo "Invalid term data for {$category->id}\n";
          }
          else {
            $obj['category'][] = $term->name;
          }
        }
      }
    }

    if (property_exists($node, 'author')) {
      if ($author = $api->getUser($node->author->id)) {
        $obj += ['author' => $author->name];
      }
    }

    foreach (['5.x', '6.x', '7.x', '8.x'] as $core) {
      if ($api->checkRelease($node->field_project_machine_name, $core)) {
        $obj['compatibility'][] = $core;
      }
    }

    $batch[] = $obj;
  }

  $index->addObjects($batch);

  echo "Done!\n";

  $page = property_exists($data, 'next') ? ++$page : FALSE;
} while($page);

