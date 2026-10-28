---
title: Vibe Check
template: default
description: Vibe Assurance scoring and content aura for GSpot CMS
---

# Vibe Assurance (Gemzy Special)

WordPress asks if your PHP parses. GSpot asks if your **vision lands**.

Vibe-Check is a heuristic MCP tool that scores brand resonance alongside technical health. No cloud AI required in v0; agents and humans get a structured report.

## Content aura

Add optional frontmatter on any page:

```yaml
vibe: synthwave
bpm: 174
energy: high
```

The starter theme exposes `data-vibe`, `data-bpm`, and `data-energy` on `<body>` and applies light CSS accents (synesthetic admin chrome is planned).

## MCP tools

### `gspot_vibe_check`

Analyze a page slug or raw markdown/HTML.

```json
{ "slug": "home" }
```

Returns `score` (0–100), `grade`, `pass` (score ≥ 60, no critical flags), `flags`, `hints`, and parsed `aura`.

### `gspot_assembly_recipe`

Returns an ordered checklist of MCP tools for agent assembly.

```json
{ "recipe": "groundswell-commerce", "run_vibe_check": true }
```

**Recipe: `groundswell-commerce`**

1. List/get HTML pattern (hero or manifesto)
2. Edit page + set aura
3. Apply vibe preset
4. Commerce checkout smoke
5. **Vibe-Check gate**
6. Cache clear → ship

## Find → Hit → Ship

| Beat | Action |
|------|--------|
| Find the Spot | Recipe picks pattern + product |
| Hit it | MCP tools wire content and commerce |
| Ship it | `pass: true` on vibe check (or fix hints first) |

## Limits (v0)

- Rule-based heuristics, not computer vision
- Does not auto-rewrite copy (Gemzy/Chief decide)
- SEO plugins unchanged; this is **Vibe Assurance**
