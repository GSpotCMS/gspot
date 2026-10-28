---
title: Admin Panel
template: default
description: GSpot CMS web admin panel
---

# Admin Panel

GSpot CMS includes a full web admin panel for managing your site without touching files directly.

## Accessing the Admin

Navigate to `/gspot-admin/` (configurable prefix in `system.yaml`).

Use **↗ View site** in the sidebar (or **View** on a page row) to open the public site in a new tab while you edit.

**Default credentials:** `admin` / `gspot-admin`

Change this immediately after first login.

## Features

### Dashboard
- Page count, plugin count, active theme
- Recent pages quick-access
- Quick action buttons

### Pages
- List all content pages
- Create new pages with markdown editor
- Edit existing pages (frontmatter + content)
- Delete pages

### Media Manager
- Upload files to `user/media/`
- Browse uploaded media
- Delete files
- Files accessible at `/media/filename`

### Plugins
- View installed plugins with status
- Enable/disable plugins with one click

### Themes
- List installed themes with presets and vibes
- Activate themes

### Settings
- Edit site title, description, URL
- Change active theme
- System configuration

## User Accounts

Accounts are stored as YAML files in `user/accounts/`:

```yaml
username: admin
password: $2y$10$...   # bcrypt hash
email: you@site.com
role: admin
created: 2026-07-04 15:36:00
```

## Customising the Admin Prefix

In `user/config/system.yaml`:

```yaml
admin:
  prefix: my-admin
  enabled: true
  login:
    max_attempts: 5
    lockout_seconds: 900
```

## Security (alpha)

- **CSRF** tokens on all authenticated admin POST forms (login exempt).
- **Login throttling** per IP + username (`user/.cache/login-throttle.json`).
- **Sessions** use httponly cookies, strict mode, SameSite=Strict, and regenerate on successful login.
