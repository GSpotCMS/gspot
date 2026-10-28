---
title: Content
template: default
description: Creating and managing content in GSpot CMS
---

# Content Management

GSpot CMS uses a flat-file content system. Pages are markdown files with YAML frontmatter, organised in folders that map directly to URLs.

## Page Structure

Every page is a `page.md` file inside a numbered folder:

```
user/content/
  01.home/
    page.md          → yoursite.com/
  02.about/
    page.md          → yoursite.com/about
  03.docs/
    page.md          → yoursite.com/docs
    01.installation/
      page.md        → yoursite.com/docs/installation
```

## Frontmatter

Every page starts with YAML frontmatter between `---` markers:

```yaml
---
title: Page Title
template: default
description: SEO description
date: 2026-07-04
published: true
---
```

### Available Fields

| Field | Type | Description |
|-------|------|-------------|
| `title` | string | Page title (required) |
| `template` | string | Blade template to use (default: `default`) |
| `description` | string | Meta description |
| `date` | string | Publication date |
| `published` | boolean | Whether page is visible |

## Markdown

Content below the frontmatter is Markdown via league/commonmark: **CommonMark** plus **GitHub-Flavored Markdown** (tables, task lists, strikethrough, autolinks).

Supported features:
- Headings, bold, italic, strikethrough
- Links and images
- Code blocks with syntax highlighting
- Lists (ordered and unordered)
- Blockquotes
- Tables
- HTML (inline)

## Ordering

Folder numbering controls page order: `01.home`, `02.about`, `03.blog`. The number prefix is stripped from URLs, so `02.about` becomes `/about`.

## Nested Content

Create sub-pages by nesting folders:

```
03.docs/
  page.md              → /docs
  01.getting-started/
    page.md            → /docs/getting-started
  02.advanced/
    page.md            → /docs/advanced
```
