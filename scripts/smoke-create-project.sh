#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

echo "Smoke: copy project to $TMP"
rsync -a --exclude vendor --exclude .git "$ROOT/" "$TMP/gspot/"
cd "$TMP/gspot"
composer install --no-interaction --prefer-dist --no-dev
php -r "require 'vendor/autoload.php'; echo 'autoload ok'.PHP_EOL;"
test -f bin/gspot && php bin/gspot --version || php -r "echo 'bin/gspot optional'.PHP_EOL;"
echo "Smoke create-project layout: OK"
