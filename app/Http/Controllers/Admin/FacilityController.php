<?php

namespace App\Http\Controllers\Admin;

use App\Models\Facility;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FacilityController extends FoundationController
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Facility::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Facilities/Index', [
            'filters' => $request->only(['search', 'status']),
            'facilities' => Facility::query()
                ->where('hospital_id', $hospital->id)
                ->when($request->search, fn ($query, $search) => $query->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")))
                ->when($request->status, fn ($query, $status) => $query->where('status', $status))
                ->orderByDesc('is_primary')
                ->orderBy('name')
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $this->authorize('create', Facility::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate($this->rules($hospital->id));

        $facility = DB::transaction(function () use ($validated, $hospital): Facility {
            if ($validated['is_primary'] ?? false) {
                Facility::where('hospital_id', $hospital->id)->update(['is_primary' => false]);
            }

            return Facility::create($validated + ['hospital_id' => $hospital->id]);
        });

        $audit->record('facilities.created', $facility, null, $facility->toArray());

        return back()->with('success', 'Facility created.');
    }

    public function update(Request $request, Facility $facility, AuditService $audit): RedirectResponse
    {
        $this->authorize('update', $facility);
        $validated = $request->validate($this->rules($facility->hospital_id, $facility->id));
        $before = $facility->only(array_keys($validated));

        DB::transaction(function () use ($validated, $facility): void {
            if ($validated['is_primary'] ?? false) {
                Facility::where('hospital_id', $facility->hospital_id)->whereKeyNot($facility->id)->update(['is_primary' => false]);
            }

            $facility->update($validated);
        });

        $audit->record('facilities.updated', $facility, $before, $facility->fresh()->only(array_keys($validated)));

        return back()->with('success', 'Facility updated.');
    }

    public function status(Request $request, Facility $facility, AuditService $audit): RedirectResponse
    {
        $this->authorize('activate', $facility);
        $validated = $request->validate(['status' => ['required', 'in:active,inactive']]);

        abort_if($facility->is_primary && $validated['status'] !== 'active', 422, 'The primary facility cannot be deactivated.');

        $before = $facility->only(['status']);
        $facility->update(['status' => $validated['status']]);

        $audit->record("facilities.{$validated['status']}", $facility, $before, $facility->only(['status']));

        return back()->with('success', 'Facility status updated.');
    }

    private function rules(int $hospitalId, ?int $facilityId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('facilities', 'code')->where('hospital_id', $hospitalId)->ignore($facilityId)],
            'facility_type' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'timezone' => ['nullable', 'timezone'],
            'is_primary' => ['boolean'],
            'status' => ['required', 'in:active,inactive'],
            'opening_hours' => ['nullable', 'array'],
        ];
    }
}
