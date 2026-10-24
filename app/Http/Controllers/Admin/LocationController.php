<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('admin');

        $data = $this->validated($request);

        $location = Location::query()->create($data);

        $this->audit->record($request->user(), 'location.created', $location, null, ['name' => $location->name]);

        return redirect()->route('admin.index')->with('status', 'Der Standort ist angelegt.');
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $this->authorize('admin');

        $location->fill($this->validated($request));
        $changes = array_keys($location->getDirty());

        if ($changes !== []) {
            $location->save();

            $this->audit->record($request->user(), 'location.updated', $location, null, ['fields' => $changes]);
        }

        return redirect()->route('admin.index')->with('status', 'Der Standort ist gespeichert.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return [
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
