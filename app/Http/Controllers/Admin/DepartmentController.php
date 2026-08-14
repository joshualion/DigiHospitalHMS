<?php

namespace App\Http\Controllers\Admin;

use App\Models\Department;
use App\Models\Facility;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $department = Department::create($validated + ['hospital_id' => $hospital->id]);

        $audit->record('departments.created', $department, null, $department->toArray());

        return back()->with('success', 'Department created.');
    }

    public function update(Request $request, Department $department, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage', $department);
        $validated = $request->validate($this->rules($department->hospital_id, $department->id));
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
        ];
    }
}
