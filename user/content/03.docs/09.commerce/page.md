---
title: Commerce
template: default
description: Flat-file products and Stripe Checkout
---

# Commerce plugin

> **Tiering:** Core features below are **free**. Affiliates, B2B invoice checkout, abandoned cart cron, admin refunds, subscriptions ledger, and multivendor require **[Commerce Pro](/docs/commerce-pro)** (`COMMERCE_PRO_LICENSE`).

GSpot Commerce stores products as YAML under `user/plugins/commerce/data/products/`. Orders land as YAML receipts when Stripe webhooks fire.

## Storefront

| URL | Purpose |
|-----|---------|
| `/commerce/shop` | Product grid |
| `/commerce/product/{slug}` | Product detail |
| `/commerce/cart` | Session cart |
| `/commerce/checkout` | POST → Stripe Checkout |

## Shortcodes

```markdown
[commerce-shop]

[commerce-product slug="team-dc-mug"]
```

Use template `default` (or any page). MUD template optional for shop landing pages.

## Stripe setup

1. Copy `.env.example` to `.env` at project root.
2. Set `STRIPE_SECRET_KEY` (test mode is fine).
3. Forward webhooks locally:

```bash
stripe listen --forward-to localhost:8080/commerce/webhook/stripe
```

4. Put the signing secret in `STRIPE_WEBHOOK_SECRET`.

## Coupons and inventory

- Flat-file coupons live in `user/plugins/commerce/data/coupons/` (demo: `TEAMDC10` = 10% off).
- Apply codes on the **cart** page before checkout. Stripe **promotion codes** are also enabled at Checkout.
- Product **stock** is optional in admin (blank = unlimited). Webhooks decrement stock using cart metadata.

## Digital downloads

Products can set `download_file` (path inside the product folder). After a paid webhook, customers get signed download links on the success page (token in `data/download_grants/`).

## Shipping

Configure `flat_shipping_cents` in the commerce plugin config. Applied when any cart line has `ship_required: true` (default for physical goods).

## Receipts

Paid orders write a plain-text receipt to `data/receipts/`. Set `COMMERCE_NOTIFY_EMAIL` to also `mail()` the shop owner.

## Tax regions

Edit `user/plugins/commerce/data/tax/regions.yaml`. Shoppers pick a region on the cart; tax is added as a Checkout line item (one-time carts only).

## Buyer receipts

Set `COMMERCE_BUYER_RECEIPTS=1` (default). After webhook, customers with a valid email get a plain-text receipt (including download links when present).

## Subscriptions

Products with `billing_type: subscription` require a Stripe **recurring** Price ID. Subscription carts cannot mix with one-time SKUs. Demo SKU: `team-dc-pro` (inactive until configured).

## Abandoned cart

Shoppers can save an email on the cart. A cron URL sends one reminder after idle time:

```bash
curl "http://localhost:8080/commerce/cron/abandoned?key=YOUR_COMMERCE_CRON_KEY"
```

Schedule that hourly on your host. Paid orders clear the abandoned record.

## Admin

**Admin → Commerce** manages products, **Coupons**, and **Orders**.

## Stripe Tax (automatic)

Set `STRIPE_AUTOMATIC_TAX=1` and enable Tax in the Stripe Dashboard. Checkout collects billing address; manual YAML tax lines are skipped.

## Gift cards

YAML in `data/gift_cards/` (demo `SPOT-GIFT-25`). Apply on cart; balance redeems on webhook. Currency must match the cart.

## Multi-currency

Each product has a `currency` field. Carts with mixed currencies cannot checkout until resolved.

## Subscription lifecycle

Webhooks track `customer.subscription.*` and `invoice.paid` / `invoice.payment_failed` into `data/subscriptions/`. View in **Admin → Commerce → Subscriptions**.

## Wishlist

Session wishlist at `/commerce/wishlist` · save from product pages · move all to cart.

## Affiliates

`?ref=TEAMDC` on shop URLs · commission tracked on paid orders · **Admin → Affiliates**.

## B2B invoice checkout

On the cart, enter company name and enable **Stripe invoice checkout** for `invoice_creation` on Checkout (payment mode).

## Install smoke

```bash
composer smoke-install
```

CI runs the same layout check (Packagist-ready `composer install` in a clean copy).

## Bundles

YAML bundles in `data/bundles/` (demo `spot-starter`). Shop grid and `[commerce-bundle slug="spot-starter"]`.

## Refunds

**Admin → Orders → View** issues Stripe refunds when `stripe_payment_intent` is on file. Webhook `charge.refunded` syncs status.

## Customer Portal

Link from order detail or `/commerce/portal?order=cs_...` (requires `stripe_customer_id` from checkout).

## MCP

- `gspot_commerce_checkout_smoke` — dry-run cart validation
- `gspot_list_products` / `gspot_save_product`
- `gspot_list_orders`
- `gspot_list_coupons` / `gspot_save_coupon`
- `gspot_list_gift_cards` / `gspot_save_gift_card`
