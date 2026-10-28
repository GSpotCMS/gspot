# Contributing to GSpot CMS

Thanks for helping ship the Spot. Groundswell, not fork.

## Getting started

1. Clone the repo and run `composer install`.
2. Copy or use the bundled `user/` tree for local dogfooding.
3. Run `composer test` before opening a PR.

## Pull requests

- Keep diffs focused; match existing PHP style in `system/src/`.
- Security-sensitive paths (admin, MCP file tools) must go through `GSpot\Security\PathGuard` and CSRF where applicable.
- Add or update PHPUnit tests when you change security or parsing behavior.

## Reporting issues

Use GitHub issues with steps to reproduce, PHP version, and whether admin or MCP was involved.

## License

By contributing, you agree your contributions are licensed under the project MIT license.
