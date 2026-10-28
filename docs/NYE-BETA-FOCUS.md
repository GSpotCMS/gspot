# NYE beta focus (5 scenarios)

**Public launch:** NYE 2026 · **Now:** dogfood and patch (`v1.0.x`). One scenario per session is enough.

---

## 1 · Core site + admin

**Goal:** Pages render, admin doesn’t bite.

| Step | Action |
|------|--------|
| Run | `composer serve` → `http://localhost:8080` |
| Check | Home, `/docs/commerce`, edit `user/content/01.home/page.md`, refresh |
| Admin | `/gspot-admin/` → change password → create/edit one page |
| Pass | No 500s; cache clear if stale (`php bin/gspot cache:clear` or MCP `gspot_cache_clear`) |
| Bonus | Admin **Patterns** shows **core / theme / site** layers; edit core pattern → saves under `user/pattern-overrides/` only |

---

## 2 · MCP daily driver

**Goal:** Cursor can operate the site without clicking every button.

| Step | Action |
|------|--------|
| Config | `bin/gspot-mcp` in Cursor MCP (`cwd` = project root) |
| Run | `gspot_site_info` → `gspot_list_content` → `gspot_edit_page` (tiny copy tweak) |
| Pass | Tools return JSON/text, page file on disk updates |

---

## 3 · Commerce happy path

**Goal:** Cart → Stripe test → webhook → order YAML.

| Step | Action |
|------|--------|
| Env | `.env` with `STRIPE_SECRET_KEY` (test mode) |
| Listen | `stripe listen --forward-to localhost:8080/commerce/webhook/stripe` |
| Shop | `/commerce/shop` → mug → cart → checkout (test card `4242…`) |
| Pass | Success page; new file under `user/plugins/commerce/data/orders/`; stock decrements if set |

**Agent drill:** `gspot_commerce_checkout_smoke` with `{ "items": [{ "slug": "team-dc-mug", "qty": 1 }] }` before live checkout.

---

## 4 · Vibe Assurance gate

**Goal:** Launch story = “does the vision land?”

| Step | Action |
|------|--------|
| Run | `gspot_vibe_check` with `{ "slug": "home" }` |
| Tweak | Add/remove corporate filler in a test page; re-run |
| Recipe | `gspot_assembly_recipe` with `{ "recipe": "groundswell-commerce", "run_vibe_check": true }` |
| Pass | You trust `pass` / hints for promo copy; aura fields on a page show in view-source (`data-vibe`) |

---

## 5 · Commerce Pro boundaries (optional)

**Goal:** Free core works without license; Pro unlocks when keyed.

| Step | Action |
|------|--------|
| Free | Cart shows Pro nudge; affiliates/`?ref=` ignored without license |
| Pro | `COMMERCE_PRO_LICENSE=GSPOT-PRO-TEST-DEV-KEY-001` in `.env` |
| Check | `/commerce/shop?vendor=team-dc` · admin **Vendors (Pro)** · affiliate list not blocked |
| Pass | No 500 on gated routes; clear docs match behaviour |

---

## When something fails

1. Note URL + what you clicked (or MCP args).
2. `composer test` locally.
3. Fix or file GitHub issue → tag patch when ready.

**NYE bar:** these five green on your machine (and one non-local deploy if you can) before `docs/LAUNCH-THREAD.md` goes public.
