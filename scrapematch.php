<?php

$filename = isset($_GET['filename']) ? $_GET['filename'] : '';
$number = isset($_GET['number']) ? $_GET['number'] : 0;

if ($filename) {
  $filepath = realpath('./data/fixtures/' . $filename . '.json');
  if ($filepath) {

    print '
    <script>
      function getParameterByName(name, url) {
        if (!url) url = window.location.href;
        name = name.replace(/[\[\]]/g, "\\$&");
        var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
            results = regex.exec(url);
        if (!results) return null;
        if (!results[2]) return "";
        return decodeURIComponent(results[2].replace(/\+/g, " "));
      }

      function isInt(value) {
        return (typeof value === "number") && (value % 1 === 0);
      }

      function wait(ms){
        var start = new Date().getTime();
        var end = start;
        while(end < start + ms) {
            end = new Date().getTime();
        }
      }

      url = document.URL;
    </script>
    ';

    $json = file_get_contents($filepath);
    if ($json) {
      $links = json_decode($json, TRUE);
      if ($number >= count($links)) {
        print '
        <script>
          var re = /premiershiparchive(\d{4})(\d{4})&number=(\d+)/;
          var m;
          if ((m = re.exec(url)) !== null) {
            if (m.index === re.lastIndex) {
                re.lastIndex++;
            }
            console.log(m)
            first = m[1] - 1;
            second = m[1];
            third = m[3];
            nextfile = url.replace("premiershiparchive" + m[1] + m[2] + "&number=" + third, "premiershiparchive" + first + second);
            window.location.replace(nextfile);
          }
        </script>
        ';
        print "Finished.";
        return;
      }
      $html = file_get_contents($links[$number]);
      if ($html) {
        $doc = new DOMDocument();
        // This class doesn't like HTML5 so suppress the error.
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
      }


    if ($data) {
      $parts = explode('/', $links[$number]);
      $parts = array_filter($parts);
      $fid = end($parts);
      $json = json_encode($data);
      $match_path = './data/matches/' . $filename;
      if (!file_exists($match_path)) {
        mkdir($match_path);
      }
      $filepath = realpath($match_path);
      $filename = $filepath . '/' . $fid . '.json';
      $write = file_put_contents($filename, $json);
      $title = $data['match']['home_team'] . ' ' . $data['match']['ft_home_goals'] . ' - ' . $data['match']['ft_away_goals']. ' ' . $data['match']['away_team'] . ' (' . $data['match']['date'] . ')<br>';
      print '<h1>' . $title . '</h1>';
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
  print '
  <script>
    qs = +getParameterByName("number", url);
    if (qs === null || qs === 0) {
      nexturl = url + "&number=1";
    }
    else if (isInt(qs)) {
      new_qs = qs + 1;
      nexturl = url.replace("number=" + qs, "number=" + new_qs);
    }
    window.location.replace(nexturl);
  </script>
  ';
  }

  return;
}