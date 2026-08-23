<?php

namespace App\Http\Controllers\Admin;

use App\Models\BillableService;
use App\Models\BillableServiceCategory;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\HospitalSetting;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PublicSiteItem;
use App\Models\Visit;
use App\Services\AuditService;
use App\Services\InvoiceWorkflowService;
use App\Services\ServicePricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends FoundationController
{
    public function catalogue(): Response
    {
        $this->authorize('viewAny', BillableService::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Billing/Catalogue', $this->shared($hospital->id) + [
            'categories' => BillableServiceCategory::where('hospital_id', $hospital->id)->orderBy('name')->get(),
            'services' => BillableService::with(['category', 'department:id,name', 'facilities:id,name', 'prices.facility:id,name', 'publicSiteItem:id,title,type'])
                ->where('hospital_id', $hospital->id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('billing.catalogue.manage') || $request->user()->hasRole('superadmin'), 403);
        $hospital = $this->currentHospital();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:40', Rule::unique('billable_service_categories')->where('hospital_id', $hospital->id)],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $category = BillableServiceCategory::create($validated + ['hospital_id' => $hospital->id, 'is_active' => true]);
        app(AuditService::class)->record('billing_categories.created', $category, null, $category->toArray(), actor: $request->user());

        return back()->with('success', 'Category created.');
    }

    public function storeService(Request $request): RedirectResponse
    {
        $this->authorize('create', BillableService::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate($this->serviceRules($hospital->id));
        $facilityIds = $validated['facility_ids'] ?? [];
        unset($validated['facility_ids']);
        $service = BillableService::create($validated + ['hospital_id' => $hospital->id]);
        $service->facilities()->sync($facilityIds);
        app(AuditService::class)->record('billable_services.created', $service, null, $service->load('facilities')->toArray(), actor: $request->user());

        return back()->with('success', 'Service created.');
    }

    public function updateService(Request $request, BillableService $service): RedirectResponse
    {
        $this->authorize('update', $service);
        $validated = $request->validate($this->serviceRules($service->hospital_id, $service));
        $facilityIds = $validated['facility_ids'] ?? [];
        unset($validated['facility_ids']);
        $before = $service->load('facilities')->toArray();
        $service->update($validated);
        $service->facilities()->sync($facilityIds);
        app(AuditService::class)->record('billable_services.updated', $service, $before, $service->load('facilities')->toArray(), actor: $request->user());

        return back()->with('success', 'Service updated.');
    }

    public function storePrice(Request $request, BillableService $service, ServicePricingService $pricing): RedirectResponse
    {
        $this->authorize('update', $service);
        $validated = $request->validate([
            'facility_id' => ['nullable', Rule::exists('facilities', 'id')->where('hospital_id', $service->hospital_id)],
            'currency' => ['required', 'string', 'size:3'],
            'amount_minor' => ['required', 'integer', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $pricing->createPrice($service, $validated, $request->user());

        return back()->with('success', 'Price added.');
    }

    public function invoices(Request $request): Response
    {
        $this->authorize('viewAny', Invoice::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Billing/Invoices', $this->shared($hospital->id) + [
            'patients' => Patient::where('hospital_id', $hospital->id)->latest()->limit(50)->get(['id', 'hospital_number', 'first_name', 'middle_name', 'last_name']),
            'visits' => Visit::where('hospital_id', $hospital->id)->latest()->limit(50)->get(['id', 'patient_id', 'facility_id', 'status']),
            'encounters' => ClinicalEncounter::where('hospital_id', $hospital->id)->latest()->limit(50)->get(['id', 'patient_id', 'visit_id', 'status']),
            'invoices' => Invoice::with('patient:id,hospital_number,first_name,middle_name,last_name')
                ->where('hospital_id', $hospital->id)
                ->when($request->status, fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(12)
                ->withQueryString(),
        ]);
    }

    public function storeInvoice(Request $request, InvoiceWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('create', Invoice::class);
        $hospital = $this->currentHospital();
        $settings = HospitalSetting::where('hospital_id', $hospital->id)->first();
        $validated = $request->validate([
            'patient_id' => ['required', Rule::exists('patients', 'id')->where('hospital_id', $hospital->id)],
            'facility_id' => ['nullable', Rule::exists('facilities', 'id')->where('hospital_id', $hospital->id)],
            'visit_id' => ['nullable', Rule::exists('visits', 'id')->where('hospital_id', $hospital->id)],
            'clinical_encounter_id' => ['nullable', Rule::exists('clinical_encounters', 'id')->where('hospital_id', $hospital->id)],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);
        $patient = Patient::where('hospital_id', $hospital->id)->findOrFail($validated['patient_id']);
        $invoice = $workflow->createDraft($validated + ['currency' => $validated['currency'] ?? $settings?->currency ?? $hospital->default_currency], $patient, $request->user());

        return redirect()->route('admin.invoices.show', $invoice)->with('success', 'Draft invoice created.');
    }

    public function showInvoice(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        return Inertia::render('Admin/Billing/InvoiceShow', $this->shared($invoice->hospital_id) + [
            'invoice' => $invoice->load(['patient', 'visit', 'encounter', 'lines', 'events']),
        ]);
    }

    public function addServiceLine(Request $request, Invoice $invoice, InvoiceWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('update', $invoice);
        $validated = $request->validate([
            'billable_service_id' => ['required', Rule::exists('billable_services', 'id')->where('hospital_id', $invoice->hospital_id)],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'discount_minor' => ['nullable', 'integer', 'min:0'],
        ]);
        $service = BillableService::where('hospital_id', $invoice->hospital_id)->findOrFail($validated['billable_service_id']);
        $workflow->addServiceLine($invoice, $service, $validated, $request->user());

        return back()->with('success', 'Invoice line added.');
    }

    public function addManualLine(Request $request, Invoice $invoice, InvoiceWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('update', $invoice);
        $validated = $request->validate([
            'service_name' => ['required', 'string', 'max:255'],
            'service_description' => ['nullable', 'string', 'max:2000'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'unit_price_minor' => ['required', 'integer', 'min:0'],
            'discount_minor' => ['nullable', 'integer', 'min:0'],
            'tax_rate_basis_points' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'tax_exempt' => ['boolean'],
            'manual_reason' => ['required', 'string', 'max:1000'],
        ]);
        $workflow->addManualLine($invoice, $validated, $request->user());

        return back()->with('success', 'Manual line added.');
    }

    public function issue(Request $request, Invoice $invoice, InvoiceWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('issue', $invoice);
        $workflow->issue($invoice, $request->user());

        return back()->with('success', 'Invoice issued.');
    }

    public function transition(Request $request, Invoice $invoice, InvoiceWorkflowService $workflow): RedirectResponse
    {
        $action = $request->input('action');
        $this->authorize($action === 'void' ? 'void' : 'update', $invoice);
        $validated = $request->validate([
            'action' => ['required', Rule::in(['cancel', 'void'])],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $workflow->transition($invoice, $validated['action'], $request->user(), $validated['reason']);

        return back()->with('success', 'Invoice updated.');
    }

    public function replacement(Request $request, Invoice $invoice, InvoiceWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('create', Invoice::class);
        $draft = $workflow->replacementDraft($invoice, $request->user());

        return redirect()->route('admin.invoices.show', $draft)->with('success', 'Replacement draft created.');
    }

    private function shared(int $hospitalId): array
    {
        return [
            'facilities' => Facility::where('hospital_id', $hospitalId)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'departments' => Department::where('hospital_id', $hospitalId)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'publicServiceItems' => PublicSiteItem::where('hospital_id', $hospitalId)->whereIn('type', ['service'])->orderBy('title')->get(['id', 'title', 'type']),
            'services' => BillableService::where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ];
    }

    private function serviceRules(int $hospitalId, ?BillableService $service = null): array
    {
        return [
            'billable_service_category_id' => ['required', Rule::exists('billable_service_categories', 'id')->where('hospital_id', $hospitalId)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('hospital_id', $hospitalId)],
            'public_site_item_id' => ['nullable', Rule::exists('public_site_items', 'id')->where('hospital_id', $hospitalId)],
            'code' => ['required', 'string', 'max:40', Rule::unique('billable_services')->where('hospital_id', $hospitalId)->ignore($service)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_tax_exempt' => ['boolean'],
            'tax_rate_basis_points' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_discount_eligible' => ['boolean'],
            'is_active' => ['boolean'],
            'facility_ids' => ['array'],
            'facility_ids.*' => [Rule::exists('facilities', 'id')->where('hospital_id', $hospitalId)],
        ];
    }
}
