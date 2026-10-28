# GSpot dev server (no Composer 300s script timeout)
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root
Write-Host "GSpot CMS → http://localhost:8080  (Ctrl+C to stop)" -ForegroundColor Cyan
php -S localhost:8080 -t public system/server.php
