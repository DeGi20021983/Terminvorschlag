<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Hintergrundaufgaben
|--------------------------------------------------------------------------
| Die Windows-Aufgabenplanung startet jede Minute „php artisan schedule:run“.
*/

// Warteschlange für E-Mails: arbeitet alles ab, was bereitliegt, und beendet sich dann.
Schedule::command('queue:work --stop-when-empty --max-time=55')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->runInBackground();

Schedule::command('terminvorschlag:erinnerungen')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('terminvorschlag:wunschfristen')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('terminvorschlag:loeschen')
    ->dailyAt('03:10')
    ->withoutOverlapping();
