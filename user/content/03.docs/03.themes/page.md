---
title: Themes
template: default
description: Theming and vibe system in GSpot CMS
---

# Themes & Vibes

GSpot CMS has a powerful theming system with vibe presets that let you swap your entire site aesthetic instantly.

## Bundled starter themes

| Theme | Vibe | Best for |
|-------|------|----------|
| **starter** | Purple synth default + minimal / cyber_noir vibes | Docs, demos, pattern bridge reference |
| **editorial** | Serif magazine, feature / breaking vibes | Long reads, blogs, magazines |
| **synthwave** | Neon grid + midnight / sunset_strip | Landing pages, music, night-drive energy |
| **commerce** | Storefront chrome + boutique / dark_sale | Shop pages with the commerce plugin |

Each theme ships **two theme-layer patterns** under `user/themes/{name}/patterns/`. Activate in **Admin → Themes**; core pattern fences stay the same, tokens and layout chrome change.

## Theme Structure

```
user/themes/my-theme/
  views/
    layouts/
      base.blade.php       Base HTML layout
      default.blade.php    Default page template
    partials/
      header.blade.php
      footer.blade.php
  assets/
    css/main.css
    js/main.js
    manifest.json          Asset versioning (optional)
  theme.yaml               Theme manifest
```

## Theme Manifest (`theme.yaml`)

```yaml
name: my-theme
version: 1.0.0
description: A custom GSpot theme
author: Your Name

presets:
  default:
    primary: "#6b21a8"
    secondary: "#f43f5e"
    background: "#0d0221"
    text: "#f8fafc"

vibes:
  synthwave:
    extends: default
  minimal:
    primary: "#1e293b"
    secondary: "#3b82f6"
    background: "#ffffff"
    text: "#1e293b"
```

## Vibe Presets

Vibes are CSS custom property overrides. When you apply a vibe, GSpot generates `:root` variables your CSS can consume:

```css
:root {
  --primary: #6b21a8;
  --secondary: #f43f5e;
  --background: #0d0221;
  --text: #f8fafc;
}
```

Apply vibes via MCP: `gspot_apply_vibe {"preset": "synthwave"}`

## Blade Templates

GSpot uses Laravel's Blade templating (standalone, no Laravel required).

### Template Inheritance

```blade
{{-- base.blade.php --}}
<html>
<body>
    @yield('content')
</body>
</html>

{{-- default.blade.php --}}
@extends('layouts.base')
@section('content')
    <h1>{{ $page->title() }}</h1>
    {!! $page->content() !!}
@endsection
```

### Available Variables

| Variable | Description |
|----------|-------------|
| `$page` | Current page object |
| `$site` | Site data (title, url, version) |
| `$config` | Config manager instance |
| `$theme` | ThemeManager instance |

## Creating a Theme

```bash
php bin/gspot new:theme my-theme
```

This scaffolds the full structure with a `theme.yaml` manifest and starter views.
