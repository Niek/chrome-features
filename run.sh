#!/usr/bin/env bash

set -ex # Exit on error, show commands

# Create dir to download source files to
mkdir -p src xml build

# Clean any existing files
rm -f src/* xml/* build/*

# Dfine the list of files to download and process
files=(
  "content/public/common/content_features.cc"
  "chrome/common/pref_names.h"
  "third_party/blink/renderer/platform/runtime_enabled_features.json5"
  "third_party/blink/renderer/core/frame/settings.json5"
  "third_party/blink/common/features.cc"
  #"components/autofill/core/common/autofill_features.cc"
  #"media/base/media_switches.cc"
  #"components/heavy_ad_intervention/heavy_ad_features.cc"
  #"components/content_settings/core/common/features.cc"
  #"components/feed/feed_feature_list.cc"
)

# Download the files
for file in "${files[@]}"; do
  curl -sf "https://raw.githubusercontent.com/chromium/chromium/main/${file}" -o "src/${file//\//_}"
done

# Generate doxygen output
echo -e "GENERATE_HTML=NO\nGENERATE_LATEX=NO\nGENERATE_XML=YES\nQUIET=YES\nINPUT=src\nFILE_PATTERNS=*.cc,*.h" | doxygen -

# Process the files
composer install
php run.php >build/index.html