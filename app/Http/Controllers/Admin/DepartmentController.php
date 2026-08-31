<?php

namespace App\Http\Controllers\Admin;

use App\Models\Department;
use App\Models\Facility;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends FoundationController
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Department::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Departments/Index', [
            'filters' => $request->only(['search', 'status']),
            'facilities' => Facility::where('hospital_id', $hospital->id)->orderBy('name')->get(['id', 'name', 'code']),
            'departments' => Department::with('facility:id,name,code')
                ->where('hospital_id', $hospital->id)
                ->when($request->search, fn ($query, $search) => $query->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")))
                ->when($request->status, fn ($query, $status) => $query->where('status', $status))
                ->orderBy('display_order')
                ->orderBy('name')
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage', Department::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate($this->rules($hospital->id));
        $validated['public_slug'] = $this->publicSlug(
            $validated['public_slug'] ?? null,
            $validated['public_name'] ?? $validated['name'],
            (bool) ($validated['public_is_visible'] ?? false)
        );
        $department = Department::create($validated + ['hospital_id' => $hospital->id]);

        $audit->record('departments.created', $department, null, $department->toArray());

        return back()->with('success', 'Department created.');
    }

    public function update(Request $request, Department $department, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage', $department);
        $validated = $request->validate($this->rules($department->hospital_id, $department->id));
        $validated['public_slug'] = $this->publicSlug(
            $validated['public_slug'] ?? null,
            $validated['public_name'] ?? $validated['name'],
            (bool) ($validated['public_is_visible'] ?? false)
        );
        $before = $department->only(array_keys($validated));
        $department->update($validated);

        $audit->record('departments.updated', $department, $before, $department->only(array_keys($validated)));

        return back()->with('success', 'Department updated.');
    }

    private function rules(int $hospitalId, ?int $departmentId = null): array
    {
        return [
            'facility_id' => ['nullable', Rule::exists('facilities', 'id')->where('hospital_id', $hospitalId)],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('departments', 'code')->where('hospital_id', $hospitalId)->ignore($departmentId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', 'string', 'max:100'],
            'status' => ['required', 'in:active,inactive'],
            'display_order' => ['integer', 'min:0'],
            'public_is_visible' => ['boolean'],
            'public_is_featured' => ['boolean'],
            'public_slug' => ['nullable', 'string', 'max:255', Rule::unique('departments', 'public_slug')->where('hospital_id', $hospitalId)->ignore($departmentId)],
            'public_name' => ['nullable', 'string', 'max:255'],
            'public_description' => ['nullable', 'string', 'max:2000'],
            'public_icon' => ['nullable', 'string', 'max:80'],
            'public_image_path' => ['nullable', 'string', 'max:255'],
            'public_display_order' => ['integer', 'min:0'],
        ];
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
