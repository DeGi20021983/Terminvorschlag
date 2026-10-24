<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\PurgeRun;
use Illuminate\Contracts\View\View;

/**
 * Administration: Grundeinstellungen, Rollen, Löschfristen und Protokoll.
 */
class AdminController extends Controller
{
    public function index(): View
    {
        $this->authorize('admin');

        return view('admin.index', [
            'locations' => Location::query()->orderBy('name')->get(),
            'roles' => [
                ['label' => 'Mitarbeitende', 'group' => 'Alle Angemeldeten', 'rights' => 'Angebote ansehen, buchen, stornieren, umbuchen'],
                ['label' => 'Organisator', 'group' => config('directory.groups.organizer') ?: 'nicht konfiguriert', 'rights' => 'Eigene Angebote und Termine verwalten, Teilnehmerliste, Wunschverteilung'],
                ['label' => 'Administrator', 'group' => config('directory.groups.admin') ?: 'nicht konfiguriert', 'rights' => 'Grundeinstellungen, Löschfristen, Protokoll'],
            ],
            'lastPurge' => PurgeRun::query()->latest('started_at')->first(),
        ]);
    }
}
