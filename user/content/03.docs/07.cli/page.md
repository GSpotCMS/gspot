---
title: CLI Reference
template: default
description: GSpot CMS command-line interface
---

# CLI Reference

GSpot CMS includes a CLI tool at `bin/gspot` for common development and management tasks.

## Commands

### `gspot serve [host] [port]`

Start the built-in PHP development server.

```bash
php bin/gspot serve                  # localhost:8080
php bin/gspot serve 0.0.0.0 9000    # all interfaces, port 9000
```

### `gspot cache:clear`

Clear compiled Blade views and page cache.

```bash
php bin/gspot cache:clear
```

### `gspot new:plugin <name>`

Scaffold a new plugin with boilerplate files.

```bash
php bin/gspot new:plugin my-analytics
# Creates: user/plugins/my-analytics/
#   - plugin.yaml
#   - MyAnalyticsPlugin.php
#   - views/
```

### `gspot new:theme <name>`

Scaffold a new theme with directory structure.

```bash
php bin/gspot new:theme dark-mode
# Creates: user/themes/dark-mode/
#   - theme.yaml
#   - views/layouts/default.blade.php
#   - assets/css/
#   - assets/js/
```

### `gspot kit:install <name>`

Install a starter kit (demo content + theme configuration).

```bash
php bin/gspot kit:install agency
```

### `gspot version`

Display the current GSpot CMS version.

```bash
php bin/gspot version
# GSpot CMS v0.1.0-alpha
```

### `gspot help`

Show available commands and usage information.
