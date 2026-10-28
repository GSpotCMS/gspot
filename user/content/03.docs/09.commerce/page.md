---
title: Commerce
template: default
description: Flat-file products and Stripe Checkout
---

# Commerce plugin

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

## Admin

**Admin → Commerce** manages products. **Orders** lists webhook-filed receipts.

## MCP

- `gspot_list_products` / `gspot_save_product`
- `gspot_list_orders`
