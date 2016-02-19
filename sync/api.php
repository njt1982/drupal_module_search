<?php

class DrupalApi {

  protected $client;

  protected $cache = [];

  function __construct() {
    $this->client = new GuzzleHttp\Client([
      'base_uri' => 'https://www.drupal.org/api-d7/',
    ]);
  }

  public function getNodes($page = 0, $limit = 50, $type = 'project_module', $status = 1) {
    return $this->client->request('GET', 'node.json', [
      'query' => [
        'type' => $type,
        'status' => $status,
        'limit' => $limit,
        'page' => $page,
      ]
    ]);
  }

  public function getTerm($tid) {
    return $this->get("taxonomy_term/{$tid}.json");
  }

  public function getUser($uid) {
    return $this->get("user/{$uid}.json");
  }


  protected function get($url) {
    $cache_path = 'cache/' . md5($url);

    if (isset($this->cache[$cache_path])) {
      return $this->cache[$cache_path];
    }

    if (file_exists($cache_path)) {
      $this->cache[$cache_path] = json_decode(file_get_contents($cache_path));
    }
    else {
      $res = $this->client->request('GET', $url);
      file_put_contents($cache_path, $res->getBody());
      $this->cache[$cache_path] = json_decode($res->getBody());
    }

    return $this->cache[$cache_path];
  }

}