# GSpot CMS

**Find the Spot. Hit it. Ship it.**

A flat-file, MCP-native PHP content management system.

## Requirements

- PHP 8.2+
- Composer

## Quick Start

From GitHub (until [Packagist](https://packagist.org) is live):

```bash
composer create-project gspot/gspot my-site --repository='{"type":"vcs","url":"https://github.com/GSpotCMS/gspot"}'
cd my-site
composer install
composer serve
```

Or clone and install:

```bash
git clone https://github.com/GSpotCMS/gspot.git my-site
cd my-site
composer install
composer serve
```

Visit `http://localhost:8080` · admin at `/gspot-admin/` (change default password immediately).

**Packagist:** submit once per [docs/PACKAGIST.md](docs/PACKAGIST.md), then `composer create-project gspot/gspot my-site` works without the VCS flag.

## Highlights (1.0)

- **Commerce** — flat-file products, Stripe Checkout, MCP smoke tool
- **Commerce Pro** — licensed multivendor, affiliates, B2B, refunds ([docs](user/content/03.docs/10.commerce-pro/page.md))
- **Vibe Assurance** — `gspot_vibe_check` + content aura ([docs](user/content/03.docs/11.vibe-check/page.md))

## Directory Structure

```
gspot/
  bin/gspot          CLI tool
  system/            Core engine (don't edit)
  user/
    content/         Your pages (folder = route)
    themes/          Your themes
    plugins/         Your plugins
    config/          Your config overrides
    media/           Uploaded files
```

## Philosophy

1. **Flat-file** — No database. Content in markdown. Config in YAML.
2. **MCP-native** — AI tooling is core, not an addon.
3. **Blade templates** — Clean, expressive, familiar.
4. **Plugin everything** — Events, hooks, extend without hacking core.
5. **Vibe presets** — Change the whole aesthetic from one manifest.

## License

MIT
