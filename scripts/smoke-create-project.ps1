$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$Tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("gspot-smoke-" + [guid]::NewGuid().ToString("n"))
New-Item -ItemType Directory -Path $Tmp | Out-Null
try {
    Write-Host "Smoke: robocopy to $Tmp"
    robocopy $Root $Tmp /E /XD vendor .git /NFL /NDL /NJH /NJS | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "robocopy failed" }
    Push-Location $Tmp
    composer install --no-interaction --prefer-dist --no-dev
    php -r "require 'vendor/autoload.php'; echo 'autoload ok' . PHP_EOL;"
    if (Test-Path "bin/gspot") { php bin/gspot --version }
    Write-Host "Smoke create-project layout: OK"
} finally {
    Pop-Location -ErrorAction SilentlyContinue
    Remove-Item -Recurse -Force $Tmp -ErrorAction SilentlyContinue
}
