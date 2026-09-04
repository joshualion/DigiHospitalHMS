<?php

namespace App\Http\Controllers\Admin;

use App\Models\BillableService;
use App\Models\BillableServiceCategory;
use App\Models\Department;
use App\Models\Facility;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends FoundationController
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BillableService::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Services/Index', [
            'filters' => $request->only(['search', 'status', 'public']),
            'categories' => BillableServiceCategory::where('hospital_id', $hospital->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'departments' => Department::where('hospital_id', $hospital->id)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'facilities' => Facility::where('hospital_id', $hospital->id)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'services' => BillableService::with(['category:id,name,code', 'department:id,name', 'facilities:id,name'])
                ->where('hospital_id', $hospital->id)
                ->when($request->search, fn ($query, $search) => $query->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('public_name', 'like', "%{$search}%")))
                ->when($request->status === 'active', fn ($query) => $query->where('is_active', true))
                ->when($request->status === 'inactive', fn ($query) => $query->where('is_active', false))
                ->when($request->public === 'visible', fn ($query) => $query->where('public_is_visible', true))
                ->when($request->public === 'featured', fn ($query) => $query->where('public_is_visible', true)->where('public_is_featured', true))
                ->when($request->public === 'private', fn ($query) => $query->where('public_is_visible', false))
                ->orderBy('public_display_order')
                ->orderBy('name')
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $this->authorize('create', BillableService::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate($this->rules($hospital->id));
        $this->prepareServicePayload($validated, $hospital->id);

        $facilityIds = $validated['facility_ids'] ?? [];
        unset($validated['facility_ids']);

        $service = BillableService::create($validated + ['hospital_id' => $hospital->id]);
        $service->facilities()->sync($facilityIds);

        $audit->record('services.created', $service, null, $service->load('facilities')->toArray(), actor: $request->user());

        return back()->with('success', 'Service created.');
    }

    public function update(Request $request, BillableService $service, AuditService $audit): RedirectResponse
    {
        $this->authorize('update', $service);
        $validated = $request->validate($this->rules($service->hospital_id, $service));
        $this->prepareServicePayload($validated, $service->hospital_id, $service);

        $facilityIds = $validated['facility_ids'] ?? [];
        unset($validated['facility_ids']);

        $before = $service->load('facilities')->toArray();
        $service->update($validated);
        $service->facilities()->sync($facilityIds);

        $audit->record('services.updated', $service, $before, $service->load('facilities')->toArray(), actor: $request->user());

        return back()->with('success', 'Service updated.');
    }

    public function destroy(BillableService $service, AuditService $audit): RedirectResponse
    {
        $this->authorize('delete', $service);

        $before = $service->load(['facilities', 'prices'])->toArray();
        $service->facilities()->detach();
        $service->delete();

        $audit->record('services.deleted', null, $before, null, actor: request()->user());

        return back()->with('success', 'Service deleted.');
    }

    private function rules(int $hospitalId, ?BillableService $service = null): array
    {
        return [
            'billable_service_category_id' => ['nullable', Rule::exists('billable_service_categories', 'id')->where('hospital_id', $hospitalId)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('hospital_id', $hospitalId)],
            'code' => ['nullable', 'string', 'max:40', Rule::unique('billable_services')->where('hospital_id', $hospitalId)->ignore($service)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'public_is_visible' => ['boolean'],
            'public_is_featured' => ['boolean'],
            'public_slug' => ['nullable', 'string', 'max:255', Rule::unique('billable_services', 'public_slug')->where('hospital_id', $hospitalId)->ignore($service)],
            'public_name' => ['nullable', 'string', 'max:255'],
            'public_description' => ['nullable', 'string', 'max:2000'],
            'public_icon' => ['nullable', 'string', 'max:80'],
            'public_image_path' => ['nullable', 'string', 'max:255'],
            'public_display_order' => ['integer', 'min:0'],
            'facility_ids' => ['array'],
            'facility_ids.*' => [Rule::exists('facilities', 'id')->where('hospital_id', $hospitalId)],
        ];
    }

    private function prepareServicePayload(array &$validated, int $hospitalId, ?BillableService $service = null): void
    {
        $validated['billable_service_category_id'] = ($validated['billable_service_category_id'] ?? null) ?: $this->defaultCategory($hospitalId)->id;
        $validated['code'] = $this->serviceCode($hospitalId, $validated['code'] ?? null, $validated['name'], $service);
        $validated['public_slug'] = $this->publicSlug(
            $validated['public_slug'] ?? null,
            $validated['public_name'] ?? $validated['name'],
            (bool) ($validated['public_is_visible'] ?? false)
        );
        $validated['public_name'] = $validated['public_name'] ?? null;
        $validated['public_description'] = $validated['public_description'] ?? null;
        $validated['public_icon'] = $validated['public_icon'] ?? null;
        $validated['public_image_path'] = $validated['public_image_path'] ?? null;
        $validated['description'] = $validated['description'] ?? null;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        $validated['public_is_visible'] = (bool) ($validated['public_is_visible'] ?? false);
        $validated['public_is_featured'] = (bool) ($validated['public_is_featured'] ?? false);
        $validated['is_tax_exempt'] = true;
        $validated['tax_rate_basis_points'] = 0;
        $validated['is_discount_eligible'] = true;
    }

    private function defaultCategory(int $hospitalId): BillableServiceCategory
    {
        return BillableServiceCategory::firstOrCreate(
            ['hospital_id' => $hospitalId, 'code' => 'PUBLIC'],
            ['name' => 'Public services', 'description' => 'Default category for public website service records.', 'is_active' => true],
        );
    }

    private function serviceCode(int $hospitalId, ?string $code, string $name, ?BillableService $service = null): string
    {
        if (filled($code)) {
            return Str::upper(Str::slug($code, '_'));
        }

        $base = Str::upper(Str::slug($name, '_')) ?: 'SERVICE';
        $base = Str::limit($base, 32, '');
        $candidate = $base;
        $suffix = 2;

        while (BillableService::where('hospital_id', $hospitalId)->where('code', $candidate)->when($service, fn ($query) => $query->whereKeyNot($service->id))->exists()) {
            $candidate = Str::limit($base, 35 - strlen((string) $suffix), '')."_{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    private function publicSlug(?string $slug, string $fallback, bool $isPublic): ?string
    {
        if (! $isPublic && blank($slug)) {
            return null;
        }

        if (blank($slug) && blank($fallback)) {
            return null;
        }

        return Str::slug($slug ?: $fallback);
    }
}
