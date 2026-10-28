# GSpot CMS — Launch Checklist (October 31, 2026)

## Pre-Launch (October 1–30)

### Repository
- [x] GitHub repo: `GSpotCMS/gspot` (public `main`)
- [x] LICENSE file (MIT)
- [x] CONTRIBUTING.md
- [x] CODE_OF_CONDUCT.md
- [x] .github/ISSUE_TEMPLATE/ configured
- [x] GitHub Actions CI (PHPUnit + lint)
- [ ] Packagist registration: `gspot/gspot` (see `docs/PACKAGIST.md`)
- [x] Release tags: `v0.3.0` … `v0.10.0` · **v1.0.0** on launch track

### Documentation
- [x] Installation guide
- [x] Content management guide
- [x] Theme development guide
- [x] Plugin development guide
- [x] Admin panel docs
- [x] MCP tools reference (commerce + Vibe Assurance updated for 1.0)
- [x] CLI reference
- [x] API reference
- [ ] Video walkthroughs (Kersey narrates)

### Security
- [x] Input sanitisation audit (PathGuard: slugs, plugin/theme names, filenames)
- [x] CSRF token on all admin POST forms (login exempt)
- [x] Path traversal prevention (PathGuard on MCP + admin file ops)
- [x] Rate limiting on login attempts (LoginThrottle + `system.admin.login.*`)
- [x] Session security hardening (SessionGuard: httponly, strict, SameSite, regenerate on login)
- [x] Dependency audit (`composer audit` in CI)

### Performance
- [x] Page caching layer (configurable TTL, mtime-aware keys)
- [x] Compiled view caching (already implemented)
- [x] Static asset versioning (theme assetUrl + plugin CSS query)
- [ ] Opcache recommendations in docs

### Promo Site
- [x] gspotcms.com promo built (GSpotCMS/ folder)
- [x] Blipvert video
- [x] Jingle audio
- [x] OG image + favicon
- [ ] Deploy promo to gspotcms.com
- [ ] Point docs.gspotcms.com to GSpot-dogfooded docs site

### Community
- [ ] Discord server created
- [ ] Welcome channel with Getting Started guide
- [ ] #plugins channel
- [ ] #themes channel
- [ ] #gspot-dev channel
- [ ] Announce in relevant PHP/CMS communities

### Marketing
- [x] "What does the G stand for? Guess." campaign concept
- [x] Kersey voice clips
- [x] Blipvert ad
- [ ] Twitter/X announcement thread
- [ ] YouTube launch video
- [ ] Dev.to / Hashnode launch article
- [ ] Reddit r/php, r/webdev posts

## Launch Day (October 31 — Halloween)

- [ ] Tag **v1.0.0** release (code at `Application::VERSION = 1.0.0` when tagged)
- [ ] `composer create-project gspot/gspot` verified working (Packagist or VCS repo flag)
- [ ] Promo site live
- [ ] Docs site live
- [ ] Discord open
- [ ] Social media blitz
- [ ] Blipvert campaign goes live
- [ ] "Find the Spot. Hit it. Ship it." launch video drops

## Inspired Digital Foundation (Post-Launch)

- [ ] Foundation Charter drafted
- [ ] Cursy Licensing terms defined
- [ ] Cursy Repo (package registry) spec
- [ ] Founding Members: Damo (Chief), Kersey (Bot Co-organiser)
- [ ] 2x Community Member seats (elected)
- [ ] 1x Corporate Partner seat
- [ ] Governance model documented
- [ ] Foundation website
