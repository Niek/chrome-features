#!/usr/bin/env bash

set -euo pipefail

# Create dir to download source files to
mkdir -p src xml build

# Clean any existing files
rm -f src/* xml/* build/*

# Define the list of files to download and process
files=(
  # Core Chromium feature/settings inputs
  "content/public/common/content_features.cc"
  "third_party/blink/renderer/platform/runtime_enabled_features.json5"
  "third_party/blink/renderer/core/frame/settings.json5"
  "third_party/blink/common/features.cc"

  # Extra feature definitions
  "components/autofill/core/common/autofill_features.cc"
  "media/base/media_switches.cc"
  "components/heavy_ad_intervention/heavy_ad_features.cc"
  "components/content_settings/core/common/features.cc"
  "components/feed/feed_feature_list.cc"

  # Chrome and component preference names
  "chrome/common/pref_names.h"
  "chrome/browser/media/prefs/pref_names.cc"
  "chrome/browser/media/prefs/pref_names.h"
  "chrome/browser/metrics/profile_pref_names.cc"
  "chrome/browser/metrics/profile_pref_names.h"
  "chrome/browser/new_tab_page/ntp_pref_names.h"
  "chrome/browser/pdf/pdf_pref_names.cc"
  "chrome/browser/pdf/pdf_pref_names.h"
  "chrome/browser/prefetch/pref_names.cc"
  "chrome/browser/prefetch/pref_names.h"
  "chrome/browser/signin/chrome_signin_pref_names.h"
  "chrome/browser/ui/tabs/saved_tab_groups/saved_tab_group_pref_names.cc"
  "chrome/browser/ui/tabs/saved_tab_groups/saved_tab_group_pref_names.h"
  "chrome/browser/ui/toolbar/toolbar_pref_names.cc"
  "chrome/browser/ui/toolbar/toolbar_pref_names.h"
  "chrome/browser/webauthn/webauthn_pref_names.cc"
  "chrome/browser/webauthn/webauthn_pref_names.h"
  "components/autofill/core/common/autofill_prefs.h"
  "components/blocked_content/pref_names.cc"
  "components/blocked_content/pref_names.h"
  "components/bookmarks/common/bookmark_pref_names.h"
  "components/browsing_data/core/pref_names.cc"
  "components/browsing_data/core/pref_names.h"
  "components/certificate_transparency/pref_names.cc"
  "components/certificate_transparency/pref_names.h"
  "components/collaboration/public/pref_names.cc"
  "components/collaboration/public/pref_names.h"
  "components/commerce/core/pref_names.h"
  "components/component_updater/pref_names.cc"
  "components/component_updater/pref_names.h"
  "components/content_settings/core/common/pref_names.h"
  "components/contextual_search/pref_names.h"
  "components/custom_handlers/pref_names.cc"
  "components/custom_handlers/pref_names.h"
  "components/device_signals/core/browser/pref_names.cc"
  "components/device_signals/core/browser/pref_names.h"
  "components/dom_distiller/core/pref_names.h"
  "components/embedder_support/origin_trials/pref_names.cc"
  "components/embedder_support/origin_trials/pref_names.h"
  "components/embedder_support/pref_names.h"
  "components/enterprise/browser/reporting/common_pref_names.cc"
  "components/enterprise/browser/reporting/common_pref_names.h"
  "components/enterprise/content/pref_names.cc"
  "components/enterprise/content/pref_names.h"
  "components/feature_engagement/public/pref_names.cc"
  "components/feature_engagement/public/pref_names.h"
  "components/feed/core/common/pref_names.cc"
  "components/feed/core/common/pref_names.h"
  "components/feed/core/shared_prefs/pref_names.cc"
  "components/feed/core/shared_prefs/pref_names.h"
  "components/history/core/common/pref_names.cc"
  "components/history/core/common/pref_names.h"
  "components/language/core/browser/pref_names.h"
  "components/live_caption/pref_names.cc"
  "components/live_caption/pref_names.h"
  "components/media_router/common/pref_names.cc"
  "components/media_router/common/pref_names.h"
  "components/metrics/dwa/dwa_pref_names.cc"
  "components/metrics/dwa/dwa_pref_names.h"
  "components/metrics/metrics_pref_names.h"
  "components/network_time/network_time_pref_names.cc"
  "components/network_time/network_time_pref_names.h"
  "components/ntp_tiles/pref_names.h"
  "components/omnibox/browser/omnibox_pref_names.h"
  "components/on_device_translation/public/pref_names.cc"
  "components/on_device_translation/public/pref_names.h"
  "components/optimization_guide/core/optimization_guide_prefs.cc"
  "components/optimization_guide/core/optimization_guide_prefs.h"
  "components/password_manager/core/common/password_manager_pref_names.h"
  "components/permissions/pref_names.cc"
  "components/permissions/pref_names.h"
  "components/policy/core/common/policy_pref_names.h"
  "components/privacy_sandbox/privacy_sandbox_prefs.h"
  "components/proxy_config/proxy_config_pref_names.h"
  "components/reading_list/core/reading_list_pref_names.cc"
  "components/reading_list/core/reading_list_pref_names.h"
  "components/safety_check/safety_check_pref_names.h"
  "components/saved_tab_groups/public/pref_names.cc"
  "components/saved_tab_groups/public/pref_names.h"
  "components/search_engines/search_engines_pref_names.h"
  "components/security_interstitials/core/pref_names.cc"
  "components/security_interstitials/core/pref_names.h"
  "components/send_tab_to_self/pref_names.cc"
  "components/send_tab_to_self/pref_names.h"
  "components/sharing_message/pref_names.h"
  "components/signin/public/base/signin_pref_names.cc"
  "components/signin/public/base/signin_pref_names.h"
  "components/site_engagement/core/pref_names.cc"
  "components/site_engagement/core/pref_names.h"
  "components/site_isolation/pref_names.cc"
  "components/site_isolation/pref_names.h"
  "components/spellcheck/browser/pref_names.h"
  "components/supervised_user/core/common/pref_names.h"
  "components/sync/base/pref_names.h"
  "components/themes/pref_names.h"
  "components/tracing/common/pref_names.cc"
  "components/tracing/common/pref_names.h"
  "components/translate/core/browser/translate_pref_names.h"
  "components/ukm/ukm_pref_names.cc"
  "components/ukm/ukm_pref_names.h"
  "components/unified_consent/pref_names.cc"
  "components/unified_consent/pref_names.h"
  "components/variations/pref_names.cc"
  "components/variations/pref_names.h"
  "components/web_resource/web_resource_pref_names.cc"
  "components/web_resource/web_resource_pref_names.h"
  "components/webui/flags/flags_ui_pref_names.cc"
  "components/webui/flags/flags_ui_pref_names.h"
  "extensions/browser/extension_pref_names.h"
  "extensions/browser/pref_names.cc"
  "extensions/browser/pref_names.h"
  "net/nqe/pref_names.cc"
  "net/nqe/pref_names.h"
  "services/preferences/public/cpp/tracked/pref_names.cc"
  "services/preferences/public/cpp/tracked/pref_names.h"
)

# Download the files
download_list="$(mktemp)"
trap 'rm -f "${download_list}"' EXIT
for file in "${files[@]}"; do
  printf '%s\0' "${file}" >>"${download_list}"
done
xargs -0 -n1 -P8 bash -c \
  'file="$1"; curl -sf "https://raw.githubusercontent.com/chromium/chromium/main/${file}" -o "src/${file//\//_}"' \
  _ <"${download_list}"

# Generate doxygen output
echo -e "GENERATE_HTML=NO\nGENERATE_LATEX=NO\nGENERATE_XML=YES\nQUIET=YES\nINPUT=src\nFILE_PATTERNS=*.cc,*.h" | doxygen -

# Process the files
composer install
php run.php >build/index.html
