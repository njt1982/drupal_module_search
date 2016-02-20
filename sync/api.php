<?php

class DrupalApi {

  protected $client;

  protected $cache = [];

  function __construct() {
    $this->client = new GuzzleHttp\Client([
      'base_uri' => 'https://www.drupal.org/api-d7/',
    ]);
  }

  public function getNodes($page, $extra = []) {
    return $this->get('node.json', [
      'query' => array_filter([
        'page' => $page,
      ] + $extra)
    ]);
  }

  public function getTerm($tid) {
    // Exclude known tids that dont exist anymore.
    switch ($tid) {
      case 14:
        return FALSE;
    }

    return $this->get("taxonomy_term/{$tid}.json");
  }
  // @todo - get terms in a batch
  // https://www.drupal.org/api-d7/taxonomy_term.json?tid[]=55&tid[]=104

  public function getUser($uid) {
    return $this->get("user/{$uid}.json");
  }

  public function getProjects($page, $limit) {
    return $this->getNodes($page, ['limit' => $limit, 'status' => 1, 'type' => 'project_module']);
  }

//  public function getReleases($nid) {
//    return $this->getNodes(0, ['type' => 'project_release', 'status' => 1, 'field_release_project' => $nid]);
//  }

  public function checkRelease($module, $core) {
    $res = $this->client->request(
      'GET',
      'https://updates.drupal.org/release-history/' . $module . '/' . $core
    );

    return strpos((string)$res->getBody(), '<project_status>published</project_status>') !== FALSE;
  }

  protected function get($url, $options = []) {
    $key = $url;
    if (!empty($options)) {
      $key .= '-' . json_encode($options);
    }
    $cache_path = 'cache/' . md5($key);

    if (isset($this->cache[$cache_path])) {
      return $this->cache[$cache_path];
    }

    if (file_exists($cache_path)) {
      $this->cache[$cache_path] = json_decode(file_get_contents($cache_path));
    }
    else {
      $res = $this->client->request('GET', $url, $options);
      file_put_contents($cache_path, $res->getBody());
      $this->cache[$cache_path] = json_decode($res->getBody());
    }

    return $this->cache[$cache_path];
  }

}