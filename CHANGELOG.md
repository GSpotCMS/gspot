# Changelog

## 1.0.3 — 2026-10-28

- Scrub legacy `mud-*` CSS/JS class prefixes → **`gspot-*`** (fences, editor preview, callout patterns).

## 1.0.2 — 2026-10-28

- **Rename:** `gspot-mud` → **`gspot-patterns`**. Shortcode **`[pattern slug="…"]`**, admin **`/patterns`**, site overrides in **`user/pattern-overrides/`**, page template **`patterns`**.

## 1.0.1 — 2026-10-28

- **MUD pattern layers:** core → theme → site (`user/pattern-overrides/`). Admin/MCP saves never mutate core.
- Starter theme demo patterns: `starter-spotlight`, `starter-minimal-cta`.

## 1.0.0 — 2026-10-28 (engineering cut; **public launch NYE 2026**)

**Find the Spot. Hit it. Ship it.**

### Core

- Flat-file pages (Markdown + YAML), Blade themes, plugin events, admin panel
- MCP server with 35+ tools (`bin/gspot-mcp`)
- Starter kits, vibe presets, page cache, CI (lint, test, smoke-install)

### Commerce (free core)

- Products, cart, Stripe Checkout, webhooks, coupons, gift cards, downloads
- Tax regions, Stripe Tax, bundles, wishlist, multi-currency
- Admin CRUD + MCP product/order/coupon tools
- `gspot_commerce_checkout_smoke` for agent dry-runs

### Commerce Pro (licensed plugin)

- `COMMERCE_PRO_LICENSE` gates affiliates, B2B invoices, abandoned cart cron, admin refunds, subscriptions ledger, multivendor v0.1

### Vibe Assurance

- `gspot_vibe_check` — brand resonance heuristics
- `gspot_assembly_recipe` — agent assembly checklists
- Content aura frontmatter (`vibe`, `bpm`, `energy`)

See git tags `v0.3.0` … `v0.10.0` for incremental commerce and Pro releases.
