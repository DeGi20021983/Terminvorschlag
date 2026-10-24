<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\PurgeRun;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Administration: Grundeinstellungen, Standorte, Rollen, Löschfristen und Protokoll.
 */
class AdminController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('admin');

        $filters = $request->validate([
            'von' => ['nullable', 'date'],
            'bis' => ['nullable', 'date'],
            'aktion' => ['nullable', 'string', 'max:64'],
            'akteur' => ['nullable', 'string', 'max:64'],
        ]);

        $audit = AuditLog::query()
            ->when($filters['von'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', CarbonImmutable::parse($from)->startOfDay()))
            ->when($filters['bis'] ?? null, fn ($query, $until) => $query->where('created_at', '<=', CarbonImmutable::parse($until)->endOfDay()))
            ->when($filters['aktion'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['akteur'] ?? null, fn ($query, $actor) => $query->where('actor_username', 'like', '%'.addcslashes($actor, '%_\\').'%'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.index', [
            'locations' => Location::query()->orderBy('name')->get(),
            'roles' => [
                ['label' => 'Mitarbeitende', 'group' => 'Alle Angemeldeten', 'rights' => 'Angebote ansehen, buchen, stornieren, umbuchen'],
                ['label' => 'Organisator', 'group' => config('directory.groups.organizer') ?: 'nicht konfiguriert', 'rights' => 'Eigene Angebote und Termine verwalten, Teilnehmerliste, Wunschverteilung'],
                ['label' => 'Administrator', 'group' => config('directory.groups.admin') ?: 'nicht konfiguriert', 'rights' => 'Grundeinstellungen, Löschfristen, Protokoll'],
            ],
            'lastPurge' => PurgeRun::query()->latest('started_at')->first(),
            'purgeRuns' => PurgeRun::query()->latest('started_at')->limit(5)->get(),
            'settings' => [
                'wish_deadline_days_before' => AppSetting::get('wish_deadline_days_before', (string) config('terminvorschlag.defaults.wish_deadline_days_before')),
                'reminder_hours_before' => AppSetting::get('reminder_hours_before', (string) config('terminvorschlag.defaults.reminder_hours_before')),
                'confirmation_text' => AppSetting::get('confirmation_text', (string) config('terminvorschlag.defaults.confirmation_text')),
            ],
            'audit' => $audit,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'filters' => $filters,
        ]);
    }
}
