# WooCommerce Herroepingsfunctie (NL)

WooCommerce compliance plugin for a public online withdrawal flow and a digital checkout waiver for immediate digital delivery.

## What It Does

- Adds a public `[herroepingsfunctie]` shortcode.
- Adds a "Herroepen" endpoint under WooCommerce My Account.
- Lets customers look up an order by order number and billing email.
- Supports partial withdrawal per order line.
- Uses a two-step confirmation flow.
- Sends an email receipt with the submitted withdrawal statement and timestamp.
- Stores withdrawal records in a dedicated table.
- Adds a WooCommerce admin overview for received withdrawals.
- Supports optional excluded categories/product IDs and optional IP logging.
- Supports WooCommerce HPOS.
- Adds a digital checkout waiver for virtual/downloadable-only carts.
- Supports classic checkout and the WooCommerce Checkout Block.
- Scopes the digital checkout waiver to configured billing countries, defaulting to EU + EEA.
- Bundles WordPress `.po`/`.mo` language files for EU official languages, EEA languages, and English.

## Legal Scope

This plugin is a technical implementation aid, not legal advice. Review all legal copy, product exclusions, country scope, checkout waiver text, and translations with counsel before production use.

The digital checkout waiver defaults to EU + EEA billing countries:

`AT, BE, BG, HR, CY, CZ, DK, EE, FI, FR, DE, GR, HU, IE, IT, LV, LT, LU, MT, NL, PL, PT, RO, SK, SI, ES, SE, IS, LI, NO`

Unknown billing country is fail-closed by default: the waiver remains visible/required until a non-scoped country is selected.

## Requirements

- WordPress 6.5+
- PHP 7.4+
- WooCommerce
- Tested locally with WordPress 7.0 and WooCommerce 8.8.2

## Installation

1. Install and activate WooCommerce.
2. Upload and activate this plugin.
3. Go to `WooCommerce > Herroeping instellingen`.
4. Configure legal copy, excluded categories/products, email subject, optional IP logging, and checkout waiver settings.
5. Create a public page such as "Herroepen / Annuleren".
6. Add the shortcode:

```text
[herroepingsfunctie]
```

7. Link the page from the footer, account area, or customer service pages.
8. Visit `Settings > Permalinks` and save once to flush rewrite rules.

## Checkout Waiver

The checkout waiver is required only when all of these are true:

- Waiver is enabled in settings.
- Cart contains only virtual/downloadable products.
- Billing country is in the configured country list.

For WooCommerce Checkout Block, the JavaScript controller mirrors the server-side rule by hiding/disabling the checkbox and restoring the default place-order label when the country is out of scope. Server-side validation remains authoritative.

## Languages

The plugin uses the `wc-herroepingsfunctie` textdomain and includes language files for:

`bg_BG, cs_CZ, da_DK, de_DE, el, en_GB, en_US, es_ES, et, fi, fr_FR, ga_IE, hr, hu_HU, is_IS, it_IT, lt_LT, lv, mt_MT, nb_NO, nl_NL, pl_PL, pt_PT, ro_RO, sk_SK, sl_SI, sv_SE`

Regional fallbacks are included for common European locales, for example:

- `nl_BE -> nl_NL`
- `fr_BE -> fr_FR`
- `de_AT -> de_DE`
- `it_CH -> it_IT`

Default setting values are translated while they still match the built-in defaults. Merchant-edited legal text is preserved and not silently machine-translated.

The bundled translations are machine-assisted drafts. Review them before production use.

## Repository Files

- `wc-herroepingsfunctie.php` - main plugin file.
- `assets/js/checkout-blocks.js` - Checkout Block waiver UI controller.
- `languages/` - bundled POT/PO/MO files.
- `readme.txt` - WordPress.org-compatible plugin readme.
- `README.md` - GitHub-facing documentation.
- `herroepingsrecht-wetgeving-compliance.md` - implementation/legal mapping notes.
- `AGENTS.md` - development ethos and verification checklist.

## Development Checks

Run the relevant subset before committing:

```bash
php -l wc-herroepingsfunctie.php
node --check assets/js/checkout-blocks.js
git diff --check
for f in languages/*.po; do msgfmt -c -o /tmp/check.mo "$f" || exit 1; done; rm -f /tmp/check.mo
```

For checkout behavior changes, verify against a live WooCommerce install with both an EU/EEA billing country and a non-scoped billing country.

## Build A Release ZIP

Release archives are ignored and should be built from committed code:

```bash
mkdir -p releases
git archive --format=zip --prefix=wc-herroepingsfunctie/ HEAD -o releases/wc-herroepingsfunctie-1.1.3.zip
unzip -t releases/wc-herroepingsfunctie-1.1.3.zip
```

## Changelog

### 1.1.3

- Added WordPress textdomain loading.
- Added bundled `.po` and `.mo` translation files for EU-official languages, EEA languages, and English.
- Added regional locale fallback for common European WordPress locales.
- Localized default setting values while preserving merchant-edited legal copy.
- Added WordPress.org-compatible `readme.txt`.
- Added this GitHub `README.md`.

### 1.1.2

- Scoped the digital checkout waiver to configured billing countries, defaulting to EU + EEA.
- Added fail-closed handling for unknown billing country.
- Updated Checkout Block behavior to hide/show the waiver and button label based on billing country.

### 1.1.1

- Added support for WooCommerce 8.8 Checkout Block waiver behavior.

### 1.1.0

- Added digital withdrawal waiver checkout flow.

### 1.0.0

- Initial online withdrawal function with shortcode, account endpoint, email confirmation, and admin log.

## License

GPL-2.0-or-later.
