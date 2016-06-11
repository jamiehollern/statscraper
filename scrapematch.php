<?php

error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);
ini_set('xdebug.var_display_max_depth', -1);
ini_set('xdebug.var_display_max_children', -1);
ini_set('xdebug.var_display_max_data', -1);

$filename = isset($_GET['filename']) ? $_GET['filename'] : '';

if ($filename) {
  $filepath = realpath('./data/fixtures/' . $filename . '.json');

  if ($filepath) {

    $json = file_get_contents($filepath);
    if ($json) {
      $data = json_decode($json, TRUE);
      $html = file_get_contents($data[0]);
      /*foreach ($data as $url) {
        $page = file_get_contents($url);
        print $page;
      }*/
      $doc = new DOMDocument();
      // This class doesn't like HTML5 so supress the error.
      @$doc->loadHTML($html);
      // Convert it to simplexml.
      $sxml = simplexml_import_dom($doc);
      // Create the rough data structure.
      $data = [
        'match' => [
          'goalscorers' => [],
        ],
        'extra_info' => [],
        'stats' => [],
        'squads' => [],
        'incidents' => [
          'substitutions' => [],
          'yellow cards' => [],
          'red cards' => [],
        ],
      ];
      // Match data.
      $data['match']['date'] = (string) $sxml->body->xpath('//div[@id="bottom_line"]/div')[0];
      $data['match']['home_team'] = (string) $sxml->body->xpath('//div[@id="score_line"]/div[@class="left"]/div[@class="title"]/a')[0];
      $data['match']['away_team'] = (string) $sxml->body->xpath('//div[@id="score_line"]/div[@class="right"]/div[@class="title"]/a')[0];
      $data['match']['ft_home_goals'] = (string) $sxml->body->xpath('//div[@id="hscore"]')[0];
      $data['match']['ft_away_goals'] = (string) $sxml->body->xpath('//div[@id="ascore"]')[0];
      $htscore = preg_match("/^HT (\d)-(\d)/", (string) $sxml->body->xpath('//div[@class="xtrscore"]')[0], $htgoals);
      $data['match']['ht_home_goals'] = $htgoals[1];
      $data['match']['ht_away_goals'] = $htgoals[2];
      // Goalscorers.
      $goalscorers = $sxml->body->xpath('//div[@id="detail_line"]/table/tbody/tr/td[@class="left"]|//div[@id="detail_line"]/table/tbody/tr/td[@class="right"]');
      foreach ($goalscorers as $key => $goalscorer) {
        $scorers = [];
        $squad_name = ($key == 0) ? 'home' : 'away';
        $scorers = (string) $goalscorer;
        if ($scorers) {
          $scorers = explode(')', $scorers);
          foreach ($scorers as $sc_key => $scorer) {
            if (!$scorer) {
              unset($scorers[$sc_key]);
            }
            else {
              $scorers[$sc_key] = $scorer . ')';
            }
          }
        }
        $data['match']['goalscorers'][$squad_name] = $scorers ?: [];
      }
      // Extra info.
      $extra_info = $sxml->body->xpath('//table[@class="fixture_extra_info"]/tbody/tr');
      foreach ($extra_info as $extra) {
        $data['extra_info'][(string) $extra->th] = (string) $extra->td;
      }
      // Stats.
      $stats = $sxml->body->xpath('//div[@id="statistics"]/div[@class="match_stats"]/div[@class="stat_row"]');
      foreach ($stats as $key => $stat) {
        if ($key != 0) {
          $stat_name = (string) $stat->div[1][0];
          $home_stat = (string) $stat->div[0]->div->div[0];
          $away_stat = (string) $stat->div[2]->div->div[0];
          $data['stats'][$stat_name] = [
            'home' => $home_stat,
            'away' => $away_stat,
          ];
        }
      }
      // Squads.
      $squads = $sxml->body->xpath('//div[@id="squad"]/div[@id="squad_box"]/div[@class="home squad_list"]|//div[@id="squad"]/div[@id="squad_box"]/div[@class="away squad_list"]');
      foreach ($squads as $key => $squad) {
        $squad_name = ($key == 0) ? 'home' : 'away';
        $data['squads'][$squad_name]['formation'] = (string) $squad->p->strong;
        for ($i = 0; $i < count($squad->div); $i++) {
          $selection = ($i < 11) ? 'first_11' : 'substitutes';
          $squad_no = (int) rtrim((string) $squad->div[$i]->span[0], '.');
          $data['squads'][$squad_name][$selection][$squad_no] = (string) $squad->div[$i];
        }
      }
      // Incidents.
      $incidents = $sxml->body->xpath('//div[@id="squad"]/div[@id="squad_box"]/div[@class="home squad_list"]|//div[@id="squad"]/div[@id="squad_box"]/div[@class="away squad_list"]');

      foreach ($incidents as $key => $incidents_parent) {
        $squad_name = ($key == 0) ? 'home' : 'away';
        foreach ($incidents_parent as $incident) {
          // Subs.
          $squad_no = ltrim((string) $incident->span[0], '0');
          $player = (string) $incident;
          $sub = (string) $incident->span[1];
          if ($sub) {
            $sub_string = $squad_no . ' ' . $player . ' (' . ltrim($sub, ' ') . ')';
            $data['incidents']['substitutions'][$squad_name][] = $sub_string;
          }
          // Cards.
          $card = $incident->img;
          if (count($card)) {
            $card_type = '';
            $cause = '';
            foreach ($card->attributes() as $key => $attribute) {
              if ($key == 'src') {
                $card_type = (strpos($attribute, 'yellow') !== FALSE) ? 'yellow cards' : 'red cards';
              }
              if ($key == 'alt') {
                $cause = str_replace(':', "',", $attribute);
              }
            }
            if ($card_type && $cause) {
              $card_string = $squad_no . ' ' . $player . ' ' . $cause;
              $data['incidents'][$card_type][$squad_name][] = $card_string;
            }
          }
        }
      }






      print '<pre>';
      print_r($data);
      print '</pre>';
      /*print '<pre>';
      print_r($sxml);
      print '</pre>';*/
    }
  }

  return;


  // Get the webpage and load it into a dom document.
  $html = file_get_contents($filename);
  $doc = new DOMDocument();
  // This class doesn't like HTML5 so supress the error.
  @$doc->loadHTML($html);
  // Convert it to simplexml.
  $sxml = simplexml_import_dom($doc);
  // Get all of the divs with fixtures in them.
  $fixture_divs = $sxml->body->xpath('//div[@class="fixtures"]');
  $links = [];
  // Iterate through them.
  foreach ($fixture_divs as $div) {
    $teams = $div->xpath('//div[@class="teams"]');
    foreach ($teams as $team) {
      $fixture = (string) $team->a->attributes()->href;
      $links[] = 'http://spfl.co.uk' . $fixture;
    }
  }
  $links = array_unique($links);
  if ($links) {
    $json = json_encode($links);
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