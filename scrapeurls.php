<?php

$url = isset($_GET['url']) ? $_GET['url'] : '';
if ($url) {
  // Get the webpage and load it into a dom document.
  $html = file_get_contents($url);
  $doc = new DOMDocument();
  // This class doesn't like HTML5 so supress the error.
  @$doc->loadHTML($html);
  // Convert it to simplexml.
  $sxml = simplexml_import_dom($doc);
  // Get all of the divs with fixtures in them.
  $fixture_divs = $sxml->body->xpath('//div[@class="fixtures"]');
  $data = [];
  // Iterate through them.
  foreach ($fixture_divs as $div) {
    $teams = $div->xpath('//div[@class="teams"]');
    foreach ($teams as $team) {
      $fixture = (string) $team->a->attributes()->href;
      $data[] = 'http://spfl.co.uk' . $fixture;
    }
  }
  $data = array_unique($data);
  if ($data) {
    $json = json_encode($data);
    $fixtures_path = './data/fixtures';
    if (!file_exists($fixtures_path)) {
      mkdir($fixtures_path);
    }
    $filepath = realpath('./data/fixtures');
    $url_parts = parse_url($url);
    $filename = $filepath . '/' . preg_replace('/[^ \w]+/', '', $url_parts['path']) . '.json';
    $write = file_put_contents($filename, $json);
    if ($write) {
      print 'JSON file ' . $filename . ' written.';
    }
    else {
      print 'JSON file ' . $filename . ' failed to write.';
    }
  }
  else {
    print "Couldn't figure this one out.";
  }
}