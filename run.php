<?php

require __DIR__ . '/vendor/autoload.php';

// Disable error reporting
error_reporting(0);

$Parsedown = new Parsedown();

$blinkFeatures = json5_decode(file_get_contents('src/third_party_blink_renderer_platform_runtime_enabled_features.json5'), true);
$blinkSettings = json5_decode(file_get_contents('src/third_party_blink_renderer_core_frame_settings.json5'), true);

// Parse blink features descriptions
$desc = [];
foreach (file('src/third_party_blink_renderer_platform_runtime_enabled_features.json5') as $line) {
  if (strpos(trim($line), '//') === 0) {
    $desc[] = substr(trim($line), 2);
  } else if (count($desc) > 0 && strpos($line, 'name: "') !== false) {
    $key = substr($line, strpos($line, 'name: "') + 7, -3);
    foreach ($blinkFeatures['data'] as &$d) {
      if ($d['name'] === $key) {
        $d['description'] = $Parsedown->line(join(PHP_EOL, $desc));
        break;
      }
    }
    $desc = [];
  } else if (trim($line) !== '{') {
    $desc = [];
  }
}

// Parse Chrome features
$features = [];

$lines = simplexml_load_file('xml/content__public__common__content__features_8cc.xml')->compounddef->programlisting->codeline;
function getDescription($line)
{
  global $lines;
  $description = [];

  // Count down from line
  while (true) {
    if ($line-- < 1) break;

    foreach ($lines as $l) {
      if ($l->attributes()['lineno'] == $line) {
        if (isset($l->highlight[1]) && $l->highlight[1]->attributes()['class'] == 'comment') {
          $comment = strip_tags($l->highlight[1]->asXml(), ['sp']);
          $comment = str_replace('<sp/>', ' ', $comment);
          $comment = preg_replace('/^\/\//', '', $comment);
          $description[] = trim($comment);
        } else break 2;
      }
    }
  }

  return array_reverse($description);
}

foreach (['xml/namespacefeatures.xml', 'xml/namespaceblink_1_1features.xml'] as $file) {
  foreach (simplexml_load_file($file)->compounddef->sectiondef as $i) {
    foreach ($i->memberdef as $j) {
      if ($j->definition == 'features::BASE_FEATURE' || $j->definition == 'blink::features::BASE_FEATURE') {
        $name = str_replace('"', '', $j->param[1]->type);
        $enabled = $j->param[2]->type == 'base::FEATURE_ENABLED_BY_DEFAULT';
        $line = intval($j->location->attributes()['line']);
        $description = getDescription($line);
        $features[] = ['name' => $name, 'enabled_default' => $enabled, 'line' => $line, 'description' => $description];
      }
    }
  }
}

// Parse Chrome prefs
$prefs = [];

foreach (simplexml_load_file('xml/namespaceprefs.xml')->compounddef->sectiondef as $i) {
  foreach ($i->memberdef as $j) {
    if ($j->type == 'constexpr char') {
      $f = $j->initializer[0];
      if (strpos($f, '"') !== false) {
        $name = explode('"', $f)[1];
        $keys = explode('.', $name);
        $val = '';               //holds next value to add to array
        $localArray = [];          //holds the array for this input line
        for ($i = count($keys) - 1; $i >= 0; $i--) { //go through input line in reverse order
          $localArray = [$keys[$i] => $val]; //store previous value in array
          $val = $localArray;           //store the array we just built. it will be the value in the next loop
        }
        $prefs = array_merge_recursive($prefs, $localArray);
      }
    }
  }
}

?>
<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Chrome features</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1/css/bulma.min.css">
  <script src="https://cdn.jsdelivr.net/npm/list.js@2/dist/list.min.js"></script>
</head>

<body>
  <section class="section">
    <div class="container is-fluid">
      <input class="input is-large" type="text" placeholder="Type to search settings..." id="search">
      <hr>
      <h1 class="title">
        Chrome features
      </h1>
      <p class="subtitle">
        Enable with <code>--enable-features</code>, disable with <code>--disable-features</code>:
      </p>
      <table class="table is-bordered is-striped is-narrow is-hoverable is-fullwidth">
        <thead>
          <tr>
            <th>Name</th>
            <th>Description</th>
            <th>Enabled by default</th>
          </tr>
        </thead>
        <tbody class="list">
          <?php
          foreach ($features as $f) {
            echo '<tr><td class="name">' . $f['name'] . '</td><td>' . (count($f['description']) > 0 ? '<pre>' . $Parsedown->line(implode(PHP_EOL, $f['description'])) . '</pre>' : '&mdash;') . '</td><td>' . ($f['enabled_default'] ? '✅' : '❌') . '</td></tr>';
          }
          ?>
        </tbody>
      </table>
    </div>
    <div class="container is-fluid">
      <h1 class="title">
        Blink features
      </h1>
      <p class="subtitle">
        Enable with <code>--enable-blink-features</code>, disable with <code>--disable-blink-features</code>:
      </p>
      <table class="table is-bordered is-striped is-narrow is-hoverable is-fullwidth">
        <thead>
          <tr>
            <th>Name</th>
            <th>Description</th>
            <th>Enabled by default</th>
          </tr>
        </thead>
        <tbody class="list">
          <?php
          foreach ($blinkFeatures['data'] as $f) {
            echo '<tr><td class="name">' . $f['name'] . '</td><td>' . (isset($f['description']) ? '<pre>' . $f['description'] . '</pre>' : '&mdash;') . '</td><td>' . (isset($f['status']) && $f['status'] === 'stable' ? '✅' : '❌') . '</td></tr>';
          }
          ?>
        </tbody>
      </table>
    </div>
    <div class="container is-fluid">
      <h1 class="title">
        Blink settings
      </h1>
      <p class="subtitle">
        Modify with <code>--blink-settings</code>:
      </p>
      <table class="table is-bordered is-striped is-narrow is-hoverable is-fullwidth">
        <thead>
          <tr>
            <th>Name</th>
            <th>Default</th>
            <th>Type</th>
          </tr>
        </thead>
        <tbody class="list">
          <?php
          foreach ($blinkSettings['data'] as $f) {
            echo '<tr><td class="name">' . $f['name'] . '</td><td>' . (isset($f['initial']) ? preg_replace('/^\'|\'$/', '', var_export($f['initial'], true)) : '&mdash;') . '</td><td>' . ($f['type'] ?? '&mdash;') . '</td></tr>';
          }
          ?>
        </tbody>
      </table>
    </div>
    <div class="container is-fluid">
      <h1 class="title">
        Preferences
      </h1>
      <p class="subtitle">
        The following JSON preferences can be modified in the <code>Preferences</code> file in the profile:
      </p>
      <pre><?= json_encode($prefs, JSON_PRETTY_PRINT) ?></pre>
    </div>
  </section>
  <footer class="footer">
    <div class="content has-text-centered">
      <p>
        <strong>Updated</strong>: <?= date('Y-m-d') ?>. For the source code, see the <a href="https://github.com/Niek/chrome-features">GitHub repo</a>.
      </p>
    </div>
  </footer>
  <script>
    // Wait until the DOM is fully loaded
    //document.addEventListener("DOMContentLoaded", function() {
      // Initialize List.js for each table
      var lists = [];
      document.querySelectorAll('table').forEach(t => {
        lists.push(new List(t, {
          valueNames: ['name']
        }))
      });

      // Filter the tables based on the search input
      const updateSearch = () => {
        const search = document.querySelector('#search').value;
        lists.forEach(e => e.search(search));

        // Update the URL with the search query using the history API
        const url = new URL(document.location.toString());
        url.searchParams.set('q', search);
        history.pushState({}, '', url.toString());
      }

      // Update the search when the input changes
      document.querySelector('#search').addEventListener('keyup', k => {
        updateSearch();
      });

      // Update the search when the page is loaded
      const url = new URL(document.location.toString());
      if (url.searchParams.has('q')) {
        document.querySelector('#search').value = url.searchParams.get('q');
        updateSearch();
      }
    //});
  </script>
</body>

</html>