# Browser E2E QA - 2026-06-22

No existing `docs/bugs/` format was present in this repository, so this file starts a compact living bug-log format for this plugin.

## Scope

- Branch: `codex/refactor-plugin-structure`
- Local site: `http://murphys-plugin.local/`
- Admin: `http://murphys-plugin.local/wp-admin/?localwp_auto_login=1`
- Active plugin copy: `/Users/aronprins/Local Sites/murphys-plugin/app/public/wp-content/plugins/wc-herroepingsfunctie`
- WooCommerce: `8.8.2`
- Plugin version shown in wp-admin: `1.1.3`
- Checkout type: WooCommerce Checkout Block on page ID 8
- Test product: `Test` (product ID 16), restored to virtual and downloadable after testing
- Workflow verification method: browser UI only. No WP REST API, Store API, WP-CLI, or direct database reads/writes were used for this E2E pass.

## Commits Under Test

- `6e554ba` - Refactor plugin bootstrap into includes
- `e824233` - Fix block waiver visibility for physical carts
- `4ebf7cd` - Show settings saved notices

## Test Matrix

| Area | Browser path | Result | Evidence |
| --- | --- | --- | --- |
| Active install | wp-admin Plugins screen | Pass | WooCommerce and WooCommerce Herroepingsfunctie were active; plugin version displayed as `1.1.3`. |
| Settings page | WooCommerce > Herroeping instellingen | Pass | Waiver enabled, Dutch legal text version `1.0`, EU/EER country list, and unknown-country fail-closed setting visible. |
| Settings save | Toggle IP logging on, save, toggle off, save | Pass after fix | Success notice appeared after each save; setting persisted on and off; final state restored to off. |
| Digital Checkout Block validation | Digital cart, submit without waiver | Pass | Checkout stayed on `/checkout/` and showed the waiver validation error. |
| Digital Checkout Block order | Digital cart, checked waiver, BACS payment | Pass | Order `#28` created for `Test x 2`; order confirmation showed waiver proof as `Yes`. |
| Order proof | wp-admin order `#28` | Pass | Admin order displayed waiver text, version `1.0`, timestamp, and source `checkout_block`. |
| Withdrawal lookup privacy | Invalid order `999999` with known email | Pass | Generic no-order-found message; no order details exposed. |
| Withdrawal submission | My Account > Herroepen for order `#28` | Pass | Selected `Test x 2`, submitted reason, confirmation success shown at `22-06-2026 11:50`. |
| Withdrawal duplicate guard | Lookup order `#28` after withdrawal | Pass | Form reported products were not available because they were already withdrawn or excluded. |
| Withdrawal admin list | WooCommerce > Herroepingen | Pass | Row for `#28`, customer email, `Test x 2`, and browser E2E reason visible. |
| Withdrawal order note | wp-admin order `#28` | Pass | Order note recorded withdrawal timestamp, `Test x 2`, and submitted reason. |
| Mail delivery UI | Mailpit at `http://localhost:10312/` | Pass | Customer withdrawal confirmation, admin withdrawal notification, customer order email, and admin new-order email visible for `#28`. |
| Physical cart rule | Product temporarily changed to non-virtual/non-downloadable, checkout loaded | Pass | Waiver checkbox was absent and button reverted to `Place Order`. Product restored afterward. |
| Country rule | Digital checkout country Netherlands -> United States -> Netherlands | Pass | Netherlands showed waiver and `Nu kopen (betaling verplicht)`; United States hid waiver and reverted to `Place Order`; Netherlands restored waiver. |
| Browser console | Exercised pages | Pass | No browser console errors recorded. |

## Bugs Found And Fixed

### 1. Settings saves did not show WordPress settings feedback

- Status: Fixed in `4ebf7cd`.
- Severity: Low UX/admin-confidence issue.
- Reproduction: Open `wp-admin/admin.php?page=wch-instellingen`, toggle `IP-adres loggen als extra bewijs`, save settings.
- Actual before fix: Setting persisted, but no WordPress success/error notice was rendered on the plugin settings page.
- Expected: WordPress should show the settings API feedback after saving.
- Fix: Added `settings_errors()` directly under the settings page `<h1>` in `includes/trait-wch-admin.php`.
- Verification: Browser retest showed `Instellingen opgeslagen.` after saving IP logging on and again after restoring it off.

## Prior Branch Bug Rechecked

### Physical carts must not display the digital waiver

- Status: Fixed in `e824233` and rechecked in this E2E pass.
- Reproduction: Temporarily changed product `Test` to non-virtual and non-downloadable through the product edit screen, added it to cart, opened Checkout Block.
- Expected and observed: The waiver checkbox was not visible and the checkout button displayed `Place Order`.
- Restoration: Product `Test` was restored to virtual and downloadable, and the cart was cleared.

## Notes And Limits

- The live checkout page uses the WooCommerce Checkout Block, so that is the checkout implementation fully exercised end to end.
- Classic shortcode checkout was not swapped onto the live checkout page during this pass because the current store workflow is block checkout and the request prioritized live end-to-end workflow testing.
- The public withdrawal shortcode was not present on a standalone page in the Local WP site. The same withdrawal renderer was exercised through the automatically registered My Account `Herroepen` endpoint.
- The run intentionally created order `#28` and withdrawal evidence for order `#28` in the Local WP site.

## Verification Commands

```sh
php -l wc-herroepingsfunctie.php
find includes -name '*.php' -print -exec php -l {} \;
php tests/github-release-updater-test.php
node --check assets/js/checkout-blocks.js
git diff --check
```

All commands passed after the fix.
