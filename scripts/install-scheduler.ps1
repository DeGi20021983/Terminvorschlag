<#
.SYNOPSIS
  Legt die Windows-Aufgabe an, die jede Minute „php artisan schedule:run“ startet.

.DESCRIPTION
  Die Aufgabe läuft unter einem Dienstkonto mit Zugriff auf das Anwendungsverzeichnis.
  Das Passwort wird abgefragt und nicht gespeichert.

.EXAMPLE
  .\scripts\install-scheduler.ps1 -AppPath C:\inetpub\terminvorschlag -Php C:\php\php.exe
#>
param(
    [string]$AppPath = 'C:\inetpub\terminvorschlag',
    [string]$Php = 'C:\php\php.exe',
    [string]$TaskName = 'Terminbuchung Scheduler'
)

$ErrorActionPreference = 'Stop'

$credential = Get-Credential -Message 'Dienstkonto für die Aufgabe (DOMÄNE\Benutzer)'

$action = New-ScheduledTaskAction -Execute $Php -Argument 'artisan schedule:run' -WorkingDirectory $AppPath

$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).Date `
    -RepetitionInterval (New-TimeSpan -Minutes 1) `
    -RepetitionDuration (New-TimeSpan -Days 3650)

$settings = New-ScheduledTaskSettingsSet `
    -MultipleInstances IgnoreNew `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 5) `
    -StartWhenAvailable

Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Settings $settings `
    -User $credential.UserName -Password $credential.GetNetworkCredential().Password -RunLevel Limited -Force | Out-Null

Write-Host "Aufgabe '$TaskName' ist angelegt. Sie startet jede Minute."
