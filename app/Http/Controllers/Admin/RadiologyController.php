<?php

namespace App\Http\Controllers\Admin;

use App\Models\BillableService;
use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\RadiologyAttachment;
use App\Models\RadiologyCriticalCommunication;
use App\Models\RadiologyModality;
use App\Models\RadiologyReport;
use App\Models\RadiologyRequest;
use App\Models\RadiologyStudy;
use App\Models\StaffProfile;
use App\Models\Visit;
use App\Services\AuditService;
use App\Services\RadiologyWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RadiologyController extends FoundationController
{
    public function catalogue(): Response
    {
        $this->authorize('viewAny', RadiologyStudy::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Radiology/Catalogue', array_merge($this->shared($hospital->id), [
            'modalities' => RadiologyModality::where('hospital_id', $hospital->id)->orderBy('name')->get(),
            'studies' => RadiologyStudy::with(['modality', 'billableService:id,code,name'])->where('hospital_id', $hospital->id)->orderBy('name')->get(),
        ]));
    }

    public function storeModality(Request $request): RedirectResponse
    {
        $this->authorize('create', RadiologyStudy::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate(['facility_id' => ['nullable', Rule::exists('facilities', 'id')->where('hospital_id', $hospital->id)], 'code' => ['required', 'string', 'max:40', Rule::unique('radiology_modalities')->where('hospital_id', $hospital->id)], 'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000']]);
        $modality = RadiologyModality::create($validated + ['hospital_id' => $hospital->id, 'is_active' => true]);
        app(AuditService::class)->record('radiology.modality_created', $modality, null, $modality->toArray(), actor: $request->user());

        return back()->with('success', 'Modality created.');
    }

    public function storeStudy(Request $request): RedirectResponse
    {
        $this->authorize('create', RadiologyStudy::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate(['radiology_modality_id' => ['required', Rule::exists('radiology_modalities', 'id')->where('hospital_id', $hospital->id)], 'billable_service_id' => ['nullable', Rule::exists('billable_services', 'id')->where('hospital_id', $hospital->id)], 'code' => ['required', 'string', 'max:40', Rule::unique('radiology_studies')->where('hospital_id', $hospital->id)], 'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'], 'preparation_acknowledgements' => ['nullable', 'array'], 'safety_screening_acknowledgements' => ['nullable', 'array']]);
        $study = RadiologyStudy::create($validated + ['hospital_id' => $hospital->id, 'requires_professional_validation' => true, 'is_active' => true]);
        app(AuditService::class)->record('radiology.study_created', $study, null, $study->toArray(), actor: $request->user());

        return back()->with('success', 'Study created.');
    }

    public function requests(Request $request): Response
    {
        $this->authorize('viewAny', RadiologyRequest::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Radiology/Requests', $this->shared($hospital->id) + [
            'patients' => Patient::where('hospital_id', $hospital->id)->latest()->limit(50)->get(['id', 'hospital_number', 'first_name', 'middle_name', 'last_name']),
            'visits' => Visit::where('hospital_id', $hospital->id)->latest()->limit(50)->get(['id', 'patient_id', 'facility_id', 'status']),
            'encounters' => ClinicalEncounter::where('hospital_id', $hospital->id)->latest()->limit(50)->get(['id', 'patient_id', 'visit_id', 'status']),
            'requests' => RadiologyRequest::with(['patient:id,hospital_number,first_name,middle_name,last_name', 'studies'])->where('hospital_id', $hospital->id)->when($request->status, fn ($query, $status) => $query->where('status', $status))->latest('ordered_at')->paginate(15)->withQueryString(),
        ]);
    }

    public function storeRequest(Request $request, RadiologyWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('order', RadiologyRequest::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate(['facility_id' => ['required', Rule::exists('facilities', 'id')->where('hospital_id', $hospital->id)], 'patient_id' => ['required', Rule::exists('patients', 'id')->where('hospital_id', $hospital->id)], 'visit_id' => ['nullable', Rule::exists('visits', 'id')->where('hospital_id', $hospital->id)], 'clinical_encounter_id' => ['nullable', Rule::exists('clinical_encounters', 'id')->where('hospital_id', $hospital->id)], 'radiology_study_ids' => ['required', 'array', 'min:1'], 'radiology_study_ids.*' => [Rule::exists('radiology_studies', 'id')->where('hospital_id', $hospital->id)], 'priority' => ['required', Rule::in(['routine', 'urgent'])], 'clinical_indication' => ['required', 'string', 'max:5000'], 'preparation_acknowledged' => ['nullable', 'array'], 'safety_screening_acknowledged' => ['nullable', 'array'], 'currency' => ['nullable', 'string', 'size:3']]);
        $radiologyRequest = $workflow->order($validated + ['hospital_id' => $hospital->id], $request->user());

        return redirect()->route('admin.radiology.requests.show', $radiologyRequest)->with('success', 'Radiology request ordered.');
    }

    public function show(RadiologyRequest $radiologyRequest): Response
    {
        $this->authorize('view', $radiologyRequest);

        return Inertia::render('Admin/Radiology/RequestShow', $this->shared($radiologyRequest->hospital_id) + ['radiologyRequest' => $radiologyRequest->load(['patient', 'visit', 'encounter', 'studies.study.modality', 'report.communications', 'report.amendments', 'attachments', 'invoice.lines'])]);
    }

    public function schedule(Request $request, RadiologyRequest $radiologyRequest, RadiologyWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('schedule', $radiologyRequest);
        $validated = $request->validate(['scheduled_at' => ['required', 'date'], 'room' => ['required', 'string', 'max:120'], 'equipment' => ['required', 'string', 'max:120'], 'assigned_staff_id' => ['nullable', Rule::exists('staff_profiles', 'id')->where('hospital_id', $radiologyRequest->hospital_id)]]);
        $workflow->schedule($radiologyRequest, $validated, $request->user());

        return back()->with('success', 'Radiology request scheduled.');
    }

    public function transition(Request $request, RadiologyRequest $radiologyRequest, RadiologyWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('perform', $radiologyRequest);
        $validated = $request->validate(['action' => ['required', Rule::in(['arrive', 'perform', 'reporting', 'cancel'])], 'reason' => ['nullable', 'required_if:action,cancel', 'string', 'max:1000'], 'performance_notes' => ['nullable', 'string', 'max:2000']]);
        $workflow->transition($radiologyRequest, $validated['action'], $request->user(), $validated['reason'] ?? null, $validated['performance_notes'] ?? null);

        return back()->with('success', 'Radiology status updated.');
    }

    public function saveReport(Request $request, RadiologyRequest $radiologyRequest, RadiologyWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('report', $radiologyRequest);
        $validated = $request->validate(['findings' => ['nullable', 'string', 'max:10000'], 'impression' => ['required', 'string', 'max:10000'], 'recommendations' => ['nullable', 'string', 'max:5000'], 'reporting_radiologist_id' => ['nullable', Rule::exists('staff_profiles', 'id')->where('hospital_id', $radiologyRequest->hospital_id)], 'has_critical_finding' => ['boolean'], 'critical_finding_notes' => ['nullable', 'string', 'max:5000']]);
        $workflow->saveReport($radiologyRequest, $validated, $request->user());

        return back()->with('success', 'Draft report saved.');
    }

    public function reportTransition(Request $request, RadiologyReport $report, RadiologyWorkflowService $workflow): RedirectResponse
    {
        $action = $request->input('action');
        $this->authorize($action === 'approve' || $action === 'release' ? 'approve' : 'verify', $report->request);
        $validated = $request->validate(['action' => ['required', Rule::in(['verify', 'approve', 'release'])]]);
        match ($validated['action']) {
            'verify' => $workflow->verifyReport($report, $request->user()),
            'approve' => $workflow->approveReport($report, $request->user()),
            'release' => $workflow->releaseReport($report, $request->user()),
        };

        return back()->with('success', 'Report updated.');
    }

    public function communicateCritical(Request $request, RadiologyReport $report, RadiologyWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('approve', $report->request);
        $validated = $request->validate(['communicated_to' => ['required', 'string', 'max:255'], 'method' => ['required', 'string', 'max:80'], 'notes' => ['required', 'string', 'max:2000']]);
        $workflow->communicateCritical($report, $validated, $request->user());

        return back()->with('success', 'Critical finding communication recorded.');
    }

    public function acknowledgeCritical(Request $request, RadiologyCriticalCommunication $communication, RadiologyWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('approve', $communication->report->request);
        $validated = $request->validate(['notes' => ['required', 'string', 'max:2000']]);
        $workflow->acknowledgeCritical($communication, $request->user(), $validated['notes']);

        return back()->with('success', 'Critical finding acknowledged.');
    }

    public function amend(Request $request, RadiologyReport $report, RadiologyWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('amend', $report->request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'content' => ['required', 'string', 'max:10000']]);
        $workflow->amendReport($report, $validated, $request->user());

        return back()->with('success', 'Report amendment added.');
    }

    public function uploadAttachment(Request $request, RadiologyRequest $radiologyRequest, RadiologyWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('attachments', $radiologyRequest);
        $validated = $request->validate(['attachment' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'], 'radiology_report_id' => ['nullable', Rule::exists('radiology_reports', 'id')->where('radiology_request_id', $radiologyRequest->id)]]);
        $report = isset($validated['radiology_report_id']) ? RadiologyReport::find($validated['radiology_report_id']) : null;
        $workflow->uploadAttachment($radiologyRequest, $validated['attachment'], $request->user(), $report);

        return back()->with('success', 'Attachment uploaded to quarantine.');
    }

    public function clearAttachment(Request $request, RadiologyAttachment $attachment, RadiologyWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('attachments', $attachment->request);
        $workflow->clearAttachment($attachment, $request->user());

        return back()->with('success', 'Attachment marked cleared.');
    }

    public function downloadAttachment(Request $request, RadiologyAttachment $attachment, RadiologyWorkflowService $workflow): StreamedResponse
    {
        $this->authorize('view', $attachment->request);
        abort_unless($attachment->scan_status === 'cleared' && $attachment->status === 'active', 404);
        $workflow->logAttachmentAccess($attachment, $request->user(), 'radiology.attachment_downloaded');

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function retireAttachment(Request $request, RadiologyAttachment $attachment, RadiologyWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('attachments', $attachment->request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $workflow->retireAttachment($attachment, $request->user(), $validated['reason']);

        return back()->with('success', 'Attachment retired.');
    }

    public function report(RadiologyRequest $radiologyRequest): Response
    {
        $this->authorize('view', $radiologyRequest);
        abort_unless($radiologyRequest->report && in_array($radiologyRequest->report->status, ['approved', 'released'], true), 404);

        return Inertia::render('Admin/Radiology/Report', ['radiologyRequest' => $radiologyRequest->load(['patient', 'studies', 'report.communications', 'report.amendments', 'attachments'])]);
    }

    private function shared(int $hospitalId): array
    {
        return ['facilities' => Facility::where('hospital_id', $hospitalId)->where('status', 'active')->orderBy('name')->get(['id', 'name']), 'studies' => RadiologyStudy::with('modality')->where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(), 'modalities' => RadiologyModality::where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(), 'billableServices' => BillableService::where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']), 'radiologyStaff' => StaffProfile::with('user:id,firstname,lastname,email')->where('hospital_id', $hospitalId)->where('is_active', true)->get(['id', 'user_id'])];
    }
}
