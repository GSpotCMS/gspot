# Packagist registration (maintainers)

GSpot CMS is **`gspot/gspot`** on GitHub: https://github.com/GSpotCMS/gspot

## One-time setup

1. Sign in at [packagist.org](https://packagist.org) (GitHub OAuth is fine).
2. **Submit** → paste `https://github.com/GSpotCMS/gspot`
3. Enable **auto-update** (Packagist webhook on the repo).

After the package is live:

```bash
composer create-project gspot/gspot my-site
cd my-site
composer serve
```

## Until Packagist is live

Use the VCS repository flag:

```bash
composer create-project gspot/gspot my-site --repository='{"type":"vcs","url":"https://github.com/GSpotCMS/gspot"}'
```

Verify locally:

```bash
composer smoke-install
```

## Package metadata

`composer.json` includes `homepage`, `support`, and `keywords` for Packagist discovery. Tag releases (`v1.0.0`, …) so `composer create-project` resolves stable versions.
