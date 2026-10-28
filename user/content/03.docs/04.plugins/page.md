---
title: Plugins
template: default
description: Plugin development for GSpot CMS
---

# Plugin Development

GSpot CMS plugins are self-contained packages with a YAML manifest, PHP class, and optional Blade views.

## Plugin Structure

```
user/plugins/my-plugin/
  MyPluginPlugin.php      Main plugin class
  plugin.yaml             Plugin manifest
  views/                  Plugin Blade views (optional)
```

## Plugin Manifest (`plugin.yaml`)

```yaml
name: my-plugin
version: 1.0.0
description: What this plugin does
author: Your Name
enabled: true
priority: 0
events:
  - onPageRender
  - onPageRoute
  - onAppBoot
```

## Plugin Class

```php
<?php

namespace GSpot\UserPlugins\MyPlugin;

use GSpot\Plugin\AbstractPlugin;

class MyPluginPlugin extends AbstractPlugin
{
    public function onPageRender(mixed &$page): void
    {
        // Modify page data before rendering
    }

    public function onPageRoute(mixed &$route): void
    {
        // Intercept routing
    }
}
```

## Available Events

| Event | Payload | Description |
|-------|---------|-------------|
| `onAppBoot` | Application | Fires after app initialisation |
| `onPageRoute` | Route | Before page resolution finalises |
| `onPageRender` | Page | Before template rendering |
| `onPageNotFound` | URI string | When no page matches |

## Event Priority

Set `priority` in your manifest (higher = fires first):

```yaml
priority: 10
```

## Scaffolding

```bash
php bin/gspot new:plugin my-plugin
```

## Shipped Plugins

GSpot CMS ships with three flagship plugins:

### Mambers Lite
Social profiles, follow/block graph, activity feed. Flat-file social networking.

### Forumz
Threaded discussions with categories, replies, pinning, locking, and search.

### Knowledge Base
Structured documentation with sections, articles, floating TOC, and star ratings.
