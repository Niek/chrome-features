<?php

require __DIR__ . '/vendor/autoload.php';

$Parsedown = new Parsedown();

$xml = simplexml_load_file('xml/namespacefeatures.xml');
$lines = simplexml_load_file('xml/content__features_8cc.xml')->compounddef->programlisting->codeline;
$blinkFeatures = json5_decode(file_get_contents('./runtime_enabled_features.json5'), true);
$blinkSettings = json5_decode(file_get_contents('./settings.json5'), true);

$features = [];

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

foreach ($xml->compounddef->sectiondef as $i) {
  foreach ($i->memberdef as $j) {
    if ($j->type == 'const base::Feature') {
      $f = $j->initializer[0];
      $name = explode('"', $f)[1];
      $enabled = (strpos($f, 'FEATURE_ENABLED_BY_DEFAULT') !== false);
      $line = intval($j->location->attributes()['line']);
      $description = getDescription($line);
      $features[] = ['name' => $name, 'enabled_default' => $enabled, 'line' => $line, 'description' => $description];
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
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0/css/bulma.min.css">
  <script src="https://cdn.jsdelivr.net/npm/list.js@2/dist/list.min.js"></script>
</head>

<body>
  <section class="section">
    <div class="container">
      <input class="input is-large" type="text" placeholder="Type to search settings..." id="search">
      <hr>
      <h1 class="title">
        Chrome features
      </h1>
      <p class="subtitle">
        Enable with <span class="is-family-monospace">--enable-features</span>, disable with <span class="is-family-monospace">--disable-features</span>:
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
    <div class="container">
      <h1 class="title">
        Blink features
      </h1>
      <p class="subtitle">
        Enable with <span class="is-family-monospace">--enable-blink-features</span>, disable with <span class="is-family-monospace">--disable-blink-features</span>:
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
            echo '<tr><td class="name">' . $f['name'] . '</td><td>&mdash;</td><td>' . (isset($f['status']) && $f['status'] === 'stable' ? '✅' : '❌') . '</td></tr>';
          }
          ?>
        </tbody>
      </table>
    </div>
    <div class="container">
      <h1 class="title">
        Blink settings
      </h1>
      <p class="subtitle">
        Modify with <span class="is-family-monospace">--blink-settings</span>:
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
  </section>
  <script>
    var lists = [];
    document.querySelectorAll('table').forEach(t => {
      lists.push(new List(t, {
        valueNames: ['name']
      }))
    });

    document.querySelector('#search').addEventListener('keyup', k => {
      lists.forEach(e => {
        e.search(document.querySelector('#search').value);
      });
    });
  </script>
</body>

</html>