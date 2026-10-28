---
title: MCP Tools
template: default
description: AI-native MCP tooling in GSpot CMS
---

# MCP Tools

GSpot CMS is MCP-native. The Model Context Protocol server ships in core and provides 23+ tools for AI-driven content management.

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

## Available Tools

### Content Management
| Tool | Description |
|------|-------------|
| `gspot_list_content` | List all pages |
| `gspot_create_page` | Create a new page |
| `gspot_edit_page` | Edit page content/frontmatter |
| `gspot_delete_page` | Delete a page |
| `gspot_search_content` | Full-text search across pages |

### Configuration
| Tool | Description |
|------|-------------|
| `gspot_get_config` | Read config value (dot-notation) |
| `gspot_set_config` | Write config value |
| `gspot_site_info` | Get comprehensive site info |

### Plugins
| Tool | Description |
|------|-------------|
| `gspot_list_plugins` | List installed plugins |
| `gspot_toggle_plugin` | Enable/disable plugin |
| `gspot_scaffold_plugin` | Create new plugin boilerplate |

### Themes
| Tool | Description |
|------|-------------|
| `gspot_list_themes` | List installed themes |
| `gspot_set_theme` | Activate a theme |
| `gspot_apply_vibe` | Apply a vibe preset |
| `gspot_scaffold_theme` | Create new theme boilerplate |

### Media
| Tool | Description |
|------|-------------|
| `gspot_list_media` | List media files |
| `gspot_upload_media` | Upload file (base64) |

### MUD Patterns (gspot-mud plugin)
| Tool | Description |
|------|-------------|
| `gspot_list_mud_patterns` | List HTML pattern library |
| `gspot_get_mud_pattern` | Get pattern metadata + HTML body |
| `gspot_save_mud_pattern` | Create or update a pattern |

### System
| Tool | Description |
|------|-------------|
| `gspot_backup` | Create zip backup of user directory |
| `gspot_restore` | List available backups |
| `gspot_cache_clear` | Clear view + page cache |

## Example Workflow

```
You: Create a blog post about our launch
AI: [calls gspot_create_page with slug, title, markdown content]

You: Change the theme to something darker
AI: [calls gspot_apply_vibe with "synthwave" preset]

You: Back up the site before I break something
AI: [calls gspot_backup]
```

## Extending MCP

Register custom tools in plugins:

```php
$app->events()->listen('onAppBoot', function ($app) {
    // Access MCP server and register tools
});
```
