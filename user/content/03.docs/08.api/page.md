---
title: API Reference
template: default
description: Developer API reference for GSpot CMS
---

# API Reference

Core classes and methods available to plugin and theme developers.

## Application

```php
$app = GSpot\Application::getInstance();
$app->boot();
```

| Method | Returns | Description |
|--------|---------|-------------|
| `config()` | ConfigManager | Access configuration |
| `renderer()` | BladeRenderer | Blade template rendering |
| `router()` | Router | Page routing |
| `plugins()` | PluginLoader | Plugin management |
| `events()` | EventDispatcher | Event system |
| `themes()` | ThemeManager | Theme/vibe management |
| `cache()` | CacheManager | Flat-file cache |
| `rootPath()` | string | Project root directory |

## ConfigManager

```php
$config = $app->config();
$config->get('site.title', 'Default');   // Dot-notation access
$config->set('site.title', 'New Title'); // Runtime override
$config->save('site', $data);           // Persist to YAML
$config->all();                          // Full config array
```

## Router

```php
$router = $app->router();
$route = $router->resolve('/about');     // URI → Route|null
$pages = $router->listPages();           // All top-level pages
$pages = $router->listPages('docs');     // Pages under /docs
```

## Page

```php
$page->title();       // Page title from frontmatter
$page->content();     // Rendered HTML content
$page->slug();        // URL slug
$page->template();    // Template name
$page->meta('key');   // Frontmatter field
$page->date();        // Publication date
$page->published();   // Visibility flag
```

## EventDispatcher

```php
$events = $app->events();

// Listen with priority (higher = fires first)
$events->listen('onPageRender', function (&$page) {
    // Modify page before render
}, priority: 10);

// Dispatch (payload passed by reference)
$events->dispatch('myEvent', $data);

// Check
$events->hasListeners('onPageRender');
```

## BladeRenderer

```php
$renderer = $app->renderer();
$html = $renderer->render('layouts.default', ['page' => $page]);
$renderer->share('key', $value);  // Global template variable
$renderer->exists('view.name');   // Check view exists
```

## ThemeManager

```php
$themes = $app->themes();
$themes->activeTheme();           // Current theme name
$themes->applyVibe('synthwave');  // Apply vibe preset
$themes->vibeTokens();            // Current CSS tokens
$themes->cssVariables();          // Generated :root CSS
$themes->listThemes();            // All installed themes
$themes->asset('css/main.css');   // Versioned asset URL
```

## CacheManager

```php
$cache = $app->cache();
$cache->set('key', $value, 3600);  // Store with TTL
$cache->get('key', $default);      // Retrieve
$cache->has('key');                 // Check existence
$cache->forget('key');             // Remove
$cache->clear();                   // Wipe all
```
