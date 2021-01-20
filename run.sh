#!/usr/bin/env bash

wget -q "https://raw.githubusercontent.com/chromium/chromium/master/content/public/common/content_features.cc" -O content_features.cc
wget -q "https://raw.githubusercontent.com/chromium/chromium/master/third_party/blink/renderer/platform/runtime_enabled_features.json5" -O runtime_enabled_features.json5

rm -rf xml
echo -e "GENERATE_HTML=NO\nGENERATE_LATEX=NO\nGENERATE_XML=YES\nQUIET=YES\nFILE_PATTERNS=*.cc" | doxygen -
composer install
php run.php > index.html