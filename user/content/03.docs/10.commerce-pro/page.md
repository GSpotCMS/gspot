---
title: Commerce Pro
template: default
description: Licensed advanced commerce for GSpot CMS
---

# Commerce Pro (Option B)

**Core commerce stays free** in the `commerce` plugin: products, cart, Stripe Checkout, orders, coupons, gift cards, downloads, bundles, wishlist, tax, and MCP tools.

**Commerce Pro** (`commerce-pro` plugin) unlocks ops-heavy features with a license key.

## Activate

1. Enable `user/plugins/commerce-pro` (on by default).
2. Set `COMMERCE_PRO_LICENSE=your-key` in `.env`, **or** copy your key to `user/plugins/commerce-pro/license.key`.

## Pro features

| Feature | Free | Pro |
|---------|------|-----|
| Single-store checkout | ✅ | ✅ |
| Multivendor (`vendor_slug`, shop filter) | | ✅ |
| Affiliates (`?ref=`) | | ✅ |
| Abandoned cart cron | | ✅ |
| B2B Stripe invoice checkout | | ✅ |
| Admin Stripe refunds | | ✅ |
| Subscriptions admin ledger | | ✅ |

## Multivendor (v0.1)

- Vendors: `commerce-pro/data/vendors/*.yaml`
- Products: optional `vendor_slug` field
- Storefront: `/commerce/shop?vendor=team-dc`

Stripe Connect payouts are planned; YAML includes `stripe_connect_id` for future wiring.

## Purchase

Contact FutureVision Labs / Team DC for Pro license keys (Groundswell not fork).
