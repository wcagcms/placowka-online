<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FacilityManagementController extends Controller
{
    public function edit(Facility $facility): View
    {
        $facility->load(['devices' => function ($query): void {
            $query->orderBy('archived_at')->orderBy('name');
        }]);

        $stats = [
            'devices_total' => $facility->devices()->count(),
            'devices_active' => $facility->devices()->where('is_active', true)->whereNull('archived_at')->count(),
            'devices_inactive' => $facility->devices()->where('is_active', false)->whereNull('archived_at')->count(),
            'devices_archived' => $facility->devices()->whereNotNull('archived_at')->count(),
            'open_incidents' => $facility->incidents()->whereIn('status', \App\Models\Incident::ACTIVE_STATUSES)->count(),
        ];

        return view('facility-edit', [
            'facility' => $facility,
            'stats' => $stats,
        ]);
    }

    public function update(Request $request, Facility $facility): RedirectResponse
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('facilities', 'code')->ignore($facility->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
        ]);

        $facility->forceFill([
            'code' => mb_strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'address' => ! empty($validated['address']) ? trim($validated['address']) : null,
            'contact_email' => $validated['contact_email'] ?: null,
        ])->save();

        return redirect()
            ->route('facilities.manage', $facility)
            ->with('success', 'Zapisano dane placówki.');
    }

    public function deactivate(Facility $facility): RedirectResponse
    {
        $facility->forceFill(['is_active' => false])->save();

        $facility->devices()
            ->whereNull('archived_at')
            ->update([
                'is_active' => false,
                'status' => 'offline',
            ]);

        return redirect()
            ->route('facilities.manage', $facility)
            ->with('success', 'Placówka została dezaktywowana. Aktywne urządzenia również zostały dezaktywowane.');
    }

    public function activate(Facility $facility): RedirectResponse
    {
        $facility->forceFill(['is_active' => true])->save();

        $facility->devices()
            ->whereNull('archived_at')
            ->update([
                'is_active' => true,
                'status' => 'unknown',
            ]);

        return redirect()
            ->route('facilities.manage', $facility)
            ->with('success', 'Placówka została ponownie aktywowana. Niezarchiwizowane urządzenia zostały aktywowane.');
    }
}
