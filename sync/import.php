#!/usr/bin/env php
<?php

$algolia_key = getenv('algolia_key');

if (empty($algolia_key)) {
  echo "No Algolia key. Please export one.\n";
  exit;
}

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/api.php';

$algolia = new \AlgoliaSearch\Client('A644RMPSD6', $algolia_key);
$index = $algolia->initIndex('prod_drupal_modules');

$api = new DrupalApi();


$max_pages = 800;

$page = 0;
do {
  $res = $api->getNodes($page, 2);

  $data = json_decode($res->getBody());
  // print_r($data);

  $batch = [];
  foreach ($data->list as $node) {
    $obj = [
      'objectID' => $node->nid,
      'title' => $node->title,
      'body' => strip_tags($node->body->value),
      'url' => $node->url,
      'project_type' => $node->field_project_type,
      'project_machine_name' => $node->field_project_machine_name,
      'download_count' => $node->field_download_count,
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

    // print_r($obj);
    $batch[] = $obj;
  }


  $index->addObjects($batch);

  echo "Done!\n";

  $page = property_exists($data, 'next') ? ++$page : FALSE;
} while($page);

