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
