# AGENTS.md

## Work Ethos

This repository is a WooCommerce compliance plugin. Treat changes as legal, security, and checkout-flow work, not as generic WordPress tweaks.

## Core Principles

- Verify the live behavior before assuming the code path. Check the active plugin copy, WooCommerce version, checkout type, product flags, saved settings, and browser DOM when debugging checkout issues.
- Prefer compatibility over forcing a store workflow change. Support classic checkout and Checkout Block where practical, including older WooCommerce block APIs when a live install depends on them.
- Keep legal meaning stable. Do not casually rewrite waiver, withdrawal, confirmation, or button text. If wording changes, update the stored text version and documentation.
- Fail closed for compliance. If a digital-only checkout legally requires explicit consent, server-side validation must enforce it; frontend display alone is not enough.
- Preserve evidence. Store order-level proof for customer consent or withdrawal actions: agreed flag, exact text, version, timestamp, source, order notes, and customer email confirmation where applicable.
- Keep privacy proportional. Collect the minimum data needed for proof and verification. IP logging must remain optional and documented.
- Security comes first. Sanitize all input, escape all output, verify nonces on AJAX, rate-limit public lookup/submit flows, avoid order enumeration, and never trust client-submitted item IDs or checkout state.
- Respect WooCommerce storage modes. Use HPOS-safe order APIs and avoid direct postmeta assumptions unless there is a documented compatibility fallback.
- Make small, focused commits. Each bug/security/compliance finding gets its own commit where practical, followed by a scan or test pass.
- Package from committed code. Release ZIPs belong in ignored `/releases/` and should be built from `HEAD` with `git archive`, then tested with `unzip -t`.

## Debugging Checklist

When a user says something is missing at checkout:

1. Check the live install path and active plugin version.
2. Check WooCommerce version and whether the checkout page uses shortcode or blocks.
3. Check whether the cart contains products that match the plugin rules, especially virtual/downloadable flags.
4. Inspect saved plugin settings and defaults.
5. Load checkout in the browser and verify the DOM, script version, visible text, button label, and console errors.
6. Fix the repo source, sync or reinstall into the live test site, then verify again in the browser.

## Verification Baseline

Run the relevant subset before committing:

- `php -l wc-herroepingsfunctie.php`
- `node --check assets/js/checkout-blocks.js` when JavaScript changes
- `git diff --check`
- targeted source scan for new input/output/security paths
- browser verification on the Local Woo install when checkout behavior changes
- `unzip -t releases/<zip>` after packaging

## Release Notes

- Keep plugin header `Version` and `WCH_VERSION` in sync.
- Keep `readme.txt` aligned with real WooCommerce compatibility.
- Keep `herroepingsrecht-wetgeving-compliance.md` aligned with feature behavior and legal assumptions.
- Do not commit generated release ZIPs; `/releases/` is intentionally gitignored.
