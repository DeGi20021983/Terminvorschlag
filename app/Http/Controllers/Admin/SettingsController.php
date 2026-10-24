<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('admin');

        $data = $request->validate([
            'wish_deadline_days_before' => ['required', 'integer', 'min:0', 'max:60'],
            'reminder_hours_before' => ['required', 'integer', 'min:1', 'max:168'],
            'confirmation_text' => ['required', 'string', 'max:1000'],
        ]);

        foreach ($data as $key => $value) {
            AppSetting::put($key, (string) $value);
        }

        $this->audit->record($request->user(), 'settings.updated', null, null, ['fields' => array_keys($data)]);

        return redirect()->route('admin.index')->with('status', 'Die Einstellungen sind gespeichert.');
    }
}
