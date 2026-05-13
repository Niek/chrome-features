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
        $d['description'] = $Parsedown->line(htmlentities(join(PHP_EOL, $desc)));
        break;
      }
    }
    $desc = [];
  } else if (trim($line) !== '{') {
    $desc = [];
  }
}

// Parse Chrome features and prefs
$features = [];
$prefs = [];

function getFileXmlPath($sourceFile)
{
  static $files = null;
  if ($files === null) {
    $files = [];
    foreach (simplexml_load_file('xml/index.xml')->compound as $compound) {
      if ((string)$compound->attributes()['kind'] !== 'file') continue;
      $name = (string)$compound->name;
      $path = 'xml/' . (string)$compound->attributes()['refid'] . '.xml';
      $files[$name] = $path;
      $files['src/' . $name] = $path;
    }
  }

  return $files[$sourceFile] ?? $files[basename($sourceFile)] ?? null;
}

function getCommentBefore($sourceFile, $line)
{
  static $cache = [];

  if (!isset($cache[$sourceFile])) {
    $xmlPath = getFileXmlPath($sourceFile);
    $cache[$sourceFile] = [];
    if ($xmlPath !== null && file_exists($xmlPath)) {
      foreach (simplexml_load_file($xmlPath)->compounddef->programlisting->codeline as $codeLine) {
        $cache[$sourceFile][(int)$codeLine->attributes()['lineno']] = $codeLine;
      }
    }
  }

  $description = [];
  while (--$line > 0) {
    if (!isset($cache[$sourceFile][$line])) continue;

    $comment = '';
    foreach ($cache[$sourceFile][$line]->highlight as $highlight) {
      $text = trim(html_entity_decode(str_replace('<sp/>', ' ', strip_tags($highlight->asXml(), ['sp'])), ENT_QUOTES | ENT_XML1));
      if ($text === '') continue;
      if ((string)$highlight->attributes()['class'] !== 'comment') break 2;
      $comment .= ' ' . $text;
    }

    if (trim($comment) === '') break;
    $comment = preg_replace(['/^\s*\/\//', '/^\s*\/\*+/', '/\*+\/\s*$/', '/^\s*\*\s?/'], ['', '', '', ''], trim($comment));
    $description[] = $comment;
  }

  return array_reverse($description);
}

function getParamTypes($member)
{
  $params = [];
  foreach ($member->param as $param) {
    $params[] = trim((string)$param->type);
  }
  return $params;
}

function getFeatureName($params)
{
  if (!isset($params[0])) return null;
  foreach (array_slice($params, 1) as $param) {
    if (preg_match('/^"([^"]+)"$/', $param, $matches)) return $matches[1];
  }
  $name = str_replace('"', '', $params[0]);
  return preg_replace('/^k(?=[A-Z])/', '', $name);
}

function isPrefFile($sourceFile)
{
  return preg_match('/(^|_)(pref_names|.*_pref_names|.*_prefs)\.(cc|h)$/', $sourceFile);
}

function addPreference(&$node, $name)
{
  foreach (explode('.', $name) as $key) {
    if (!isset($node[$key]) || !is_array($node[$key])) $node[$key] = [];
    $node = &$node[$key];
  }
  if ($node === []) $node = '';
}

function sortTree(&$value)
{
  if (!is_array($value)) return;
  ksort($value, SORT_NATURAL | SORT_FLAG_CASE);
  foreach ($value as &$child) {
    sortTree($child);
  }
}

$seenFeatures = [];
foreach (glob('xml/namespace*.xml') as $file) {
  foreach (simplexml_load_file($file)->compounddef->sectiondef as $section) {
    foreach ($section->memberdef as $member) {
      $sourceFile = (string)$member->location->attributes()['file'];

      if ((string)$member->name === 'BASE_FEATURE') {
        $params = getParamTypes($member);
        $name = getFeatureName($params);
        if ($name !== null && !isset($seenFeatures[$name])) {
          $features[] = [
            'name' => $name,
            'enabled_default' => in_array('base::FEATURE_ENABLED_BY_DEFAULT', $params, true),
            'description' => getCommentBefore($sourceFile, (int)$member->location->attributes()['line']),
          ];
          $seenFeatures[$name] = true;
        }
      }

      if (isPrefFile($sourceFile) && strpos((string)$member->type, 'char') !== false && preg_match('/"([^"]+)"/', (string)$member->initializer[0], $matches)) {
        $name = $matches[1];
        if (strpos($name, '.') !== false || !preg_match('/(Key|Name|Option|Options|Value|Values)$/', (string)$member->name)) {
          addPreference($prefs, $name);
        }
      }
    }
  }
}

usort($features, fn($a, $b) => strcasecmp($a['name'], $b['name']));
sortTree($prefs);

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
            echo '<tr><td class="name">' . $f['name'] . '</td><td>' . (count($f['description']) > 0 ? '<pre>' . $Parsedown->line(htmlentities(implode(PHP_EOL, $f['description']))) . '</pre>' : '&mdash;') . '</td><td>' . ($f['enabled_default'] ? '✅' : '❌') . '</td></tr>';
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
    document.addEventListener("DOMContentLoaded", function() {
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
    });
  </script>
</body>

</html>
