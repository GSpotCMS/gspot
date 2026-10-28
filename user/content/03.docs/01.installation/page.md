---
title: Installation
template: default
description: Installing GSpot CMS
---

# Installation

## Requirements

- PHP 8.2 or higher
- Composer 2.x
- A web server (Apache, Nginx, or PHP built-in for dev)

## Quick Start

```bash
composer create-project gspot/gspot my-site
cd my-site
php bin/gspot serve
```

Visit `http://localhost:8080` and you're live.

## Manual Installation

1. Clone or download the repository
2. Run `composer install`
3. Point your web server's document root at the `public/` directory
4. For development, use `php bin/gspot serve`

## Directory Structure

```
gspot/
  bin/gspot              CLI tool
  system/                Core engine (don't modify)
    src/                 PSR-4 source classes
    config/              Default configuration
    views/               System Blade views
    kits/                Starter kits
  user/                  YOUR stuff (this is what you edit)
    content/             Pages (folder = route)
    themes/              Themes
    plugins/             Plugins
    config/              Config overrides
    media/               Uploaded files
    accounts/            User accounts (flat-file)
  public/                Web root
  vendor/                Composer dependencies
```

## Configuration

The main config lives in `system/config/` (defaults) merged with `user/config/` (your overrides).

### Site Config (`user/config/site.yaml`)

```yaml
title: My Site
description: A GSpot CMS site
url: https://mysite.com
author: Your Name
```

### System Config (`user/config/system.yaml`)

```yaml
theme: starter
cache:
  enabled: true
  ttl: 3600
admin:
  prefix: gspot-admin
  enabled: true
```

## First Steps

1. Edit `user/content/01.home/page.md` to change your homepage
2. Visit `/gspot-admin/` to access the admin panel (default: admin / gspot-admin)
3. Change your password immediately
4. Start creating pages
