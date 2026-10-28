---
title: MCP Tools
template: default
description: AI-native MCP tooling in GSpot CMS
---

# MCP Tools

GSpot CMS is MCP-native. The Model Context Protocol server ships in core (**35+ tools**) via `bin/gspot-mcp`.

## Setup

Add to your Cursor MCP config (`~/.cursor/mcp.json`):

```json
{
  "mcpServers": {
    "gspot": {
      "command": "php",
      "args": ["bin/gspot-mcp"],
      "cwd": "/path/to/your/gspot/site"
    }
  }
}
```

## Content Management

| Tool | Description |
|------|-------------|
| `gspot_list_content` | List all pages |
| `gspot_create_page` | Create a new page |
| `gspot_edit_page` | Edit title, body, template, **vibe/bpm/energy** aura |
| `gspot_delete_page` | Delete a page |
| `gspot_search_content` | Full-text search across pages |

## Configuration & system

| Tool | Description |
|------|-------------|
| `gspot_get_config` / `gspot_set_config` | Read/write config (dot notation) |
| `gspot_site_info` | Version, theme, page count, paths |
| `gspot_cache_clear` | Clear compiled views + page cache |
| `gspot_backup` / `gspot_restore` | Zip backup of `user/` |

## Plugins & themes

| Tool | Description |
|------|-------------|
| `gspot_list_plugins` / `gspot_toggle_plugin` | Plugin inventory |
| `gspot_scaffold_plugin` | New plugin boilerplate |
| `gspot_list_themes` / `gspot_set_theme` | Theme switching |
| `gspot_apply_vibe` | Apply vibe preset tokens |
| `gspot_scaffold_theme` | New theme boilerplate |

## Media

| Tool | Description |
|------|-------------|
| `gspot_list_media` | Media library listing |
| `gspot_upload_media` | Upload base64 file to `/media/` |

## Patterns (gspot-patterns)

| Tool | Description |
|------|-------------|
| `gspot_list_patterns` | Pattern library index |
| `gspot_get_pattern` | Pattern HTML + metadata |
| `gspot_save_pattern` | Create/update pattern |

## Commerce (commerce plugin)

| Tool | Description |
|------|-------------|
| `gspot_list_products` / `gspot_save_product` | Product YAML CRUD |
| `gspot_list_orders` | Order receipts |
| `gspot_list_coupons` / `gspot_save_coupon` | Coupon CRUD |
| `gspot_list_gift_cards` / `gspot_save_gift_card` | Gift card CRUD |
| `gspot_list_affiliates` / `gspot_save_affiliate` | Affiliates (Commerce Pro licensed) |
| `gspot_commerce_checkout_smoke` | Dry-run cart + Stripe config validation |

## Vibe Assurance (1.0)

| Tool | Description |
|------|-------------|
| `gspot_vibe_check` | Score brand resonance (`slug` or raw `content`) |
| `gspot_assembly_recipe` | Agent checklist (e.g. `groundswell-commerce`) |

See [Vibe Check](/docs/vibe-check) for aura frontmatter and pass/fail gates.

## Example: Find → Hit → Ship

```
1. gspot_assembly_recipe { "recipe": "groundswell-commerce" }
2. gspot_edit_page + gspot_apply_vibe
3. gspot_commerce_checkout_smoke { "items": [{ "slug": "team-dc-mug", "qty": 1 }] }
4. gspot_vibe_check { "slug": "home" }
5. gspot_cache_clear → deploy
```

WordPress asks if your code works. GSpot asks if your **vision lands**.

## Extending MCP

Register custom tools from plugins on `onAppBoot` (see plugin development guide).
