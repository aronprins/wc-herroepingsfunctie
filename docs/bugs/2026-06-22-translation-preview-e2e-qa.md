# Translation Preview Browser E2E QA - 2026-06-22

## Scope

- Branch: `feature/translation-default-preview`
- Local site: `http://murphys-plugin.local/`
- Admin: `http://murphys-plugin.local/wp-admin/?localwp_auto_login=1`
- Active plugin copy: `/Users/aronprins/Local Sites/murphys-plugin/app/public/wp-content/plugins/wc-herroepingsfunctie`
- WooCommerce: `8.8.2`
- Plugin version shown in wp-admin: `1.1.3`
- Checkout type: WooCommerce Checkout Block on the live Local install
- Test product: `Test` (product ID 16), restored to virtual and downloadable after testing
- Test order created: `#29`
- Workflow verification method: browser UI only. No WP REST API, Store API, WP-CLI, or direct database reads/writes were used for the E2E pass. The branch files were synced into the active Local plugin copy before browser testing.

## Changes Under Test

- Added a settings-page dropdown for loading bundled translated defaults into editable text fields before saving.
- Added admin JavaScript that previews selected translation defaults client-side without saving.
- Added explicit saved-translation provenance so intentionally selected defaults are not silently re-localized on reload.

## Test Matrix

| Area | Browser path | Result | Evidence |
| --- | --- | --- | --- |
| Active install baseline | wp-admin Plugins screen | Pass | WooCommerce and WooCommerce Herroepingsfunctie were active; plugin version displayed as `1.1.3`. |
| Settings baseline | WooCommerce > Herroeping instellingen | Pass | Before syncing, the translation dropdown was absent and Dutch defaults were visible. |
| Branch sync verification | WooCommerce > Herroeping instellingen after file sync | Pass | Dropdown `Standaardteksten vooraf invullen` appeared with 27 bundled locales plus placeholder. |
| Unsaved preview | Select `en_GB`, reload without saving | Pass | Text fields changed to English immediately; reload restored the previously saved Dutch values. |
| Edited preview save | Select `en_GB`, customize validation notice, save | Pass after fix | Checkbox text, intro, email subject, button text, and custom validation notice persisted after reload. |
| Frontend translated settings | Checkout Block, Netherlands, digital cart | Pass | Checkout showed English waiver text and `Buy now (payment required)` while the English preview was saved. |
| Checkout validation | Submit digital checkout without waiver | Pass | Checkout stayed on `/checkout/` and showed custom validation notice `E2E test: please confirm immediate digital delivery before ordering.` |
| Checkout order | Check waiver and place BACS order | Pass | Order `#29` created for `Test x 1`; order confirmation showed waiver proof as `Yes`. |
| Order proof | wp-admin order `#29` | Pass | Admin order displayed waiver text, version `1.0`, timestamp `2026-06-22 15:18:21`, and source `checkout_block`. |
| Customer order email | Mailpit customer order email for `#29` | Pass | Email included waiver text and `Vastgelegd als versie 1.0 op 2026-06-22 15:18:21.` |
| Withdrawal lookup privacy | Invalid order `999999` with known email | Pass | Generic no-order-found message; no order details exposed. |
| Withdrawal submission | My Account > Herroepen for order `#29` | Pass | Selected `Test x 1`, submitted E2E reason, and success message appeared at `22-06-2026 15:19`. |
| Withdrawal duplicate guard | Lookup order `#29` after withdrawal | Pass | Form reported products were unavailable because they were already withdrawn or excluded. |
| Withdrawal admin list | WooCommerce > Herroepingen | Pass | Row for `#29`, customer email, `Test x 1`, and E2E reason visible. |
| Withdrawal order note | wp-admin order `#29` | Pass | Order note recorded withdrawal timestamp, `Test x 1`, and submitted reason. |
| Customer withdrawal email | Mailpit withdrawal confirmation for `#29` | Pass | Email subject used the saved English setting and body included the submitted products, timestamp, and reason. |
| Country rule | Digital checkout country Netherlands -> United States -> Netherlands | Pass | Netherlands showed waiver and custom buy button; United States hid waiver and restored `Place Order`; Netherlands restored waiver. |
| Physical cart rule | Product temporarily changed to non-virtual/non-downloadable | Pass | Checkout hid waiver and displayed `Place Order`; product was restored afterward. |
| Settings restoration | Select `nl_NL`, save | Pass | Dutch defaults restored for intro, confirmation button, email subject, waiver text, validation notice, and button text. |
| Final checkout restoration | Digital cart after Dutch restore | Pass | Checkout showed Dutch waiver text and `Nu kopen (betaling verplicht)`. |
| Final cleanup | Cart and product state | Pass | Cart emptied; product `Test` restored to virtual and downloadable. |
| Older release baseline | Install published `v1.1.3` ZIP locally, then test settings and checkout | Pass | wp-admin showed version `1.1.3`, translation dropdown/admin script were absent, Dutch digital waiver rendered, validation blocked unchecked checkout, and cart was emptied. |
| Update baseline from `v1.1.3` | Dashboard > Updates > Opnieuw controleren | Blocked as expected | The published `v1.1.3` ZIP does not include `Update URI` or the GitHub updater code, so WordPress cannot discover `v1.1.4` from that install. |
| GitHub Release `v1.1.5` | GitHub Actions tag workflow | Pass | Release `v1.1.5` published with `wc-herroepingsfunctie-1.1.5.zip` at `2026-06-22T15:43:04Z`; asset digest `sha256:29450d55d754cf78d77e8d858092b659f85b9ee5d9b8b7acb1fcfce691f94bee`. |
| Update-capable older baseline | Install published `v1.1.4` ZIP locally | Pass | Active Local plugin copy showed header `Version: 1.1.4`, `WCH_VERSION` `1.1.4`, and `Update URI: https://github.com/aronprins/wc-herroepingsfunctie`. |
| Automatic updater E2E | Dashboard > Updates, select only WooCommerce Herroepingsfunctie, Plugins updaten | Pass | WordPress showed `1.1.4` installed and `1.1.5` available; updater reported `WooCommerce Herroepingsfunctie (NL) succesvol geüpdatet.` |
| Updated install verification | wp-admin Plugins screen and active plugin header | Pass | Plugins screen showed `Versie 1.1.5`, no stale `1.1.5` update notice remained, and active plugin header/constant both showed `1.1.5`. |

## Bugs Found And Fixed

### 1. Saving English default waiver text on a Dutch site re-localized it back to Dutch

- Status: Fixed in this branch.
- Severity: Medium settings/checkout text correctness issue.
- Reproduction: On a Dutch admin/site locale, open `WooCommerce > Herroeping instellingen`, choose `English (United Kingdom) - en_GB`, customize only the validation notice, save settings, and reload the settings page.
- Actual before fix: Intro text, confirmation button, email subject, and custom validation notice persisted in English, but the waiver checkbox text and payment button text reverted to Dutch.
- Expected: All selected English preview values should persist after the merchant explicitly saves them.
- Root cause: The existing default-localization guard intentionally re-localizes saved values that exactly match the raw PHP defaults. The raw defaults for `waiver_text` and `waiver_button_text` are English, so explicitly saved English values were mistaken for auto-localizable defaults and converted to Dutch on reload.
- Fix: Added hidden settings provenance fields (`_default_translation_locale` and `_default_translation_fields`) that the admin script updates when a bundled translation is selected. Sanitization validates the provenance against the selected `.mo` translation values, and `get_settings()` skips auto-localization only for those explicitly selected default-translation fields.
- Verification: Browser retest showed saved `en_GB` waiver text and `Buy now (payment required)` persisted on the settings page and appeared in Checkout Block. After testing, selecting `nl_NL` restored the Dutch defaults.

### 2. Forced WordPress update checks could reuse stale GitHub release metadata

- Status: Fixed in follow-up release `1.1.5`.
- Severity: Medium update UX issue.
- Reproduction: Install a release that includes the GitHub updater, let it cache the current latest GitHub Release, publish a newer release, then open `Dashboard > Updates` and click `Opnieuw controleren`.
- Actual before fix: WordPress refreshes its own `update_plugins` transient, but the plugin's separate `wch_github_release_latest` transient can still return the earlier GitHub Release for up to six hours.
- Expected: An authorized admin's forced WordPress update check should also refresh this plugin's GitHub release metadata.
- Fix: Added a `load-update-core.php` hook and a defensive updater-level check that deletes `wch_github_release_latest` once per request when an admin with `update_plugins` visits `update-core.php?force-check=1`.
- Verification: Added coverage to `php tests/github-release-updater-test.php`; browser update verification passed from `v1.1.4` to `v1.1.5` because the published `v1.1.3` ZIP predates the updater.

## Observations

- Browser console logs included a few generic `InvalidStateError: Transition was aborted because of invalid state` entries on wp-admin pages and two unattributed `MutationObserver` errors. No user-facing workflow failure accompanied them, and the `MutationObserver` entries were not clearly attributable to this plugin. No code change was made for those logs.
- The withdrawal endpoint body picked up the saved English intro/confirmation texts while the plugin's fixed field labels and some transactional body copy remained Dutch. That matches the current design because only configured text fields are translated through settings.
- The run intentionally created order `#29` and withdrawal evidence for order `#29` in the Local WP site.
- After the branch E2E pass, the active Local plugin copy was replaced with the published `v1.1.3` ZIP and smoke-tested as the baseline for updater verification.
- The published `v1.1.3` ZIP cannot be used for GitHub updater E2E because it does not contain the updater header or code. The updater E2E baseline must therefore be `v1.1.4` or newer.
- For the `v1.1.4` to `v1.1.5` local updater test, stale `wch_github_release_latest` and `update_plugins` transient rows were cleared once before the browser update check. This was necessary because `v1.1.4` can cache pre-`v1.1.5` GitHub metadata; the `v1.1.5` fix is intended to prevent that same issue on future forced update checks.

## Verification Commands

```sh
php -l wc-herroepingsfunctie.php
find includes -name '*.php' -print -exec php -l {} \;
node --check assets/js/admin-settings.js
node --check assets/js/checkout-blocks.js
php tests/github-release-updater-test.php
git diff --check
```

All commands passed after the fix.
