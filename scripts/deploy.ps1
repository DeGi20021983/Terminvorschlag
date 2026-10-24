<#
.SYNOPSIS
  Stellt Terminbuchung auf dem IIS-Server bereit.

.DESCRIPTION
  Ablauf: Quellcode aktualisieren (nur Fast-Forward), Abhängigkeiten ohne Entwicklungspakete,
  Datenbank migrieren, Caches neu erzeugen, Warteschlange neu starten.
  Die Datei .env liegt auf dem Server und ist nicht im Repository.

.EXAMPLE
  .\scripts\deploy.ps1 -AppPath C:\inetpub\terminvorschlag -Php C:\php\php.exe -AppPool Terminbuchung
#>
param(
    [string]$AppPath = (Get-Location).Path,
    [string]$Branch = 'main',
    [string]$Php = 'php',
    [string]$Composer = 'composer',
    [string]$AppPool = ''
)

$ErrorActionPreference = 'Stop'

function Invoke-Step {
    param([string]$Title, [scriptblock]$Command)

    Write-Host ''
    Write-Host ('>> ' + $Title) -ForegroundColor Cyan
    & $Command

    if ($LASTEXITCODE -ne 0) {
        throw "Schritt fehlgeschlagen: $Title (Exitcode $LASTEXITCODE)"
    }
}

Set-Location -LiteralPath $AppPath

if (-not (Test-Path -LiteralPath '.env')) {
    throw '.env fehlt. Bitte die Konfiguration auf dem Server anlegen, bevor Sie bereitstellen.'
}

Invoke-Step '1/5 Quellcode aktualisieren (nur Fast-Forward)' {
    git fetch origin $Branch
    git merge --ff-only "origin/$Branch"
}

Invoke-Step '2/5 Abhängigkeiten installieren (ohne Entwicklungspakete)' {
    & $Composer install --no-dev --optimize-autoloader --no-interaction
}

Invoke-Step '3/5 Datenbank migrieren' {
    & $Php artisan migrate --force
}

Invoke-Step '4/5 Caches neu erzeugen' {
    & $Php artisan optimize:clear
    & $Php artisan config:cache
    & $Php artisan route:cache
    & $Php artisan view:cache
    & $Php artisan event:cache
}

Invoke-Step '5/5 Warteschlange neu starten' {
    & $Php artisan queue:restart
}

if ($AppPool) {
    Import-Module WebAdministration
    Restart-WebAppPool -Name $AppPool
    Write-Host "Anwendungspool $AppPool neu gestartet."
}

Write-Host ''
Write-Host 'Bereitstellung abgeschlossen.' -ForegroundColor Green
