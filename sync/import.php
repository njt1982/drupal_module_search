#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

try {
  $dotenv = new Dotenv\Dotenv(__DIR__);
  $dotenv->load();
  $dotenv->required(['ELASTIC_HOST', 'ELASTIC_USERNAME', 'ELASTIC_PASSWORD', 'ELASTIC_INDEX'])->notEmpty();
}
catch(Dotenv\Exception\ValidationException $e) {
  echo $e->getMessage();
  exit;
}
catch(Dotenv\Exception\InvalidPathException $e) {
  echo $e->getMessage();
}

$elastic_host = getenv('ELASTIC_HOST');
$elastic_username = getenv('ELASTIC_USERNAME');
$elastic_password = getenv('ELASTIC_PASSWORD');
$elastic_index = getenv('ELASTIC_INDEX');

$limit = getenv('limit') ?: 10;
echo "Importing at {$limit} per page.\n";

$client = Elasticsearch\ClientBuilder::create()
  ->setHosts([
    [
      'host' => $elastic_host,
      'port' => 9200,
      'scheme' => 'http',
      'user' => $elastic_username,
      'pass' => $elastic_password
    ]
  ])
  ->setRetries(2)
  ->build();


require __DIR__ . '/api.php';
$api = new DrupalApi();

$max_pages = 80000;
$page = 0;
do {
  echo "Getting page: {$page} ";
  $data = $api->getProjects($page, $limit);

  $batch = ['body' => []];

  foreach ($data->list as $node) {
    echo ".";
    $obj = [
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
    $batch['body'][] = ['index' => ['_index' => $elastic_index, '_type' => 'project', '_id' => $node->nid] ]; 
    $batch['body'][] = $obj;
  }

  $client->bulk($batch);

  echo "Done!\n";

  $page = property_exists($data, 'next') ? ++$page : FALSE;
} while($page);

