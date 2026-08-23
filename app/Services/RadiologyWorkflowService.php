<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\RadiologyAttachment;
use App\Models\RadiologyCriticalCommunication;
use App\Models\RadiologyEvent;
use App\Models\RadiologyReport;
use App\Models\RadiologyReportAmendment;
use App\Models\RadiologyRequest;
use App\Models\RadiologyRequestStudy;
use App\Models\RadiologyStudy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RadiologyWorkflowService
{
    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly InvoiceWorkflowService $invoices,
        private readonly AuditService $audit,
        private readonly PatientActivity $activity,
    ) {}

    public function order(array $data, User $actor): RadiologyRequest
    {
        return DB::transaction(function () use ($data, $actor): RadiologyRequest {
            $patient = Patient::where('hospital_id', $data['hospital_id'])->findOrFail($data['patient_id']);
            $studies = RadiologyStudy::with('billableService')->where('hospital_id', $data['hospital_id'])->whereIn('id', $data['radiology_study_ids'])->where('is_active', true)->get();
            abort_if($studies->isEmpty(), 422, 'At least one imaging study is required.');

            $request = RadiologyRequest::create([
                'hospital_id' => $data['hospital_id'],
                'facility_id' => $data['facility_id'],
                'patient_id' => $patient->id,
                'visit_id' => $data['visit_id'] ?? null,
                'clinical_encounter_id' => $data['clinical_encounter_id'] ?? null,
                'ordering_clinician_id' => $data['ordering_clinician_id'] ?? $actor->staffProfile?->id,
                'ordered_by' => $actor->id,
                'request_number' => $this->allocate($data['hospital_id'], 'radiology_request_number'),
                'accession_number' => $this->allocate($data['hospital_id'], 'radiology_accession_number'),
                'status' => 'ordered',
                'priority' => $data['priority'] ?? 'routine',
                'clinical_indication' => $data['clinical_indication'],
                'preparation_acknowledged' => $data['preparation_acknowledged'] ?? [],
                'safety_screening_acknowledged' => $data['safety_screening_acknowledged'] ?? [],
                'ordered_at' => now(),
            ]);

            $invoice = null;
            foreach ($studies as $study) {
                $lineId = null;
                if ($study->billable_service_id) {
                    $invoice ??= $this->invoiceFor($request, $patient, $data, $actor);
                    $lineId = $this->invoices->addServiceLine($invoice->fresh(), $study->billableService, ['quantity' => 1], $actor)->id;
                }
                RadiologyRequestStudy::create(['hospital_id' => $request->hospital_id, 'radiology_request_id' => $request->id, 'radiology_study_id' => $study->id, 'invoice_line_id' => $lineId, 'study_code' => $study->code, 'study_name' => $study->name]);
            }
            if ($invoice) {
                $request->forceFill(['invoice_id' => $invoice->id])->save();
            }

            $this->event($request, 'radiology.requested', null, 'ordered', null, $request->fresh()->toArray(), $actor);
            $this->activity->record($patient, 'radiology.requested', $actor, ['request_number' => $request->request_number]);

            return $request->refresh();
        });
    }

    public function schedule(RadiologyRequest $request, array $data, User $actor): RadiologyRequest
    {
        abort_unless(in_array($request->status, ['ordered', 'scheduled'], true), 422, 'Only ordered requests can be scheduled.');

        return DB::transaction(function () use ($request, $data, $actor): RadiologyRequest {
            $request = RadiologyRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $scheduledAt = $data['scheduled_at'];
            $conflict = RadiologyRequest::where('hospital_id', $request->hospital_id)
                ->where('facility_id', $request->facility_id)
                ->where('id', '!=', $request->id)
                ->where('status', 'scheduled')
                ->where('scheduled_at', $scheduledAt)
                ->where(function ($query) use ($data): void {
                    $query->where('room', $data['room'])->orWhere('equipment', $data['equipment']);
                    if (! empty($data['assigned_staff_id'])) {
                        $query->orWhere('assigned_staff_id', $data['assigned_staff_id']);
                    }
                })->exists();
            abort_if($conflict, 422, 'Radiology schedule conflict detected.');
            $before = $request->toArray();
            $request->forceFill(['status' => 'scheduled', 'scheduled_at' => $scheduledAt, 'room' => $data['room'], 'equipment' => $data['equipment'], 'assigned_staff_id' => $data['assigned_staff_id'] ?? null])->save();
            $this->event($request, 'radiology.scheduled', $before['status'] ?? null, 'scheduled', $before, $request->fresh()->toArray(), $actor);

            return $request->refresh();
        });
    }

    public function transition(RadiologyRequest $request, string $action, User $actor, ?string $reason = null, ?string $notes = null): RadiologyRequest
    {
        return DB::transaction(function () use ($request, $action, $actor, $reason, $notes): RadiologyRequest {
            $request = RadiologyRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $before = $request->toArray();
            $from = $request->status;
            $updates = match ($action) {
                'arrive' => $this->assertStatus($request, ['scheduled'], ['status' => 'arrived', 'arrived_at' => now()]),
                'perform' => $this->assertStatus($request, ['arrived', 'scheduled'], ['status' => 'performed', 'performed_at' => now(), 'performance_notes' => $notes]),
                'reporting' => $this->assertStatus($request, ['performed'], ['status' => 'reporting']),
                'cancel' => $this->assertStatus($request, ['ordered', 'scheduled', 'arrived'], ['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $actor->id, 'cancellation_reason' => $reason]),
                default => abort(422, 'Unsupported radiology transition.'),
            };
            $request->forceFill($updates)->save();
            $this->event($request, "radiology.{$action}", $from, $request->status, $before, $request->fresh()->toArray(), $actor, $reason);

            return $request->refresh();
        });
    }

    public function saveReport(RadiologyRequest $request, array $data, User $actor): RadiologyReport
    {
        abort_unless(in_array($request->status, ['performed', 'reporting'], true), 422, 'Reports require a performed study.');
        $report = $request->report;
        abort_if($report && in_array($report->status, ['approved', 'released'], true), 422, 'Approved reports require an amendment.');
        $payload = ['hospital_id' => $request->hospital_id, 'radiology_request_id' => $request->id, 'status' => 'draft', 'entered_by' => $actor->id, 'entered_at' => now()] + $data;
        $report = $report ? tap($report)->update($data + ['status' => 'draft']) : RadiologyReport::create($payload);
        $request->forceFill(['status' => 'reporting'])->save();
        $this->event($report, 'radiology.report_drafted', null, 'draft', null, $report->fresh()->toArray(), $actor);

        return $report->refresh();
    }

    public function verifyReport(RadiologyReport $report, User $actor): RadiologyReport
    {
        abort_unless($report->status === 'draft', 422, 'Only draft reports can be verified.');

        return $this->reportTransition($report, 'radiology.report_verified', 'verified', $actor);
    }

    public function approveReport(RadiologyReport $report, User $actor): RadiologyReport
    {
        abort_unless($report->status === 'verified', 422, 'Only verified reports can be approved.');
        abort_if($report->entered_by === $actor->id && config('hms.radiology.separate_entry_approval', true), 403, 'Report approver must be separate from entry user.');

        return $this->reportTransition($report, 'radiology.report_approved', 'approved', $actor);
    }

    public function releaseReport(RadiologyReport $report, User $actor): RadiologyReport
    {
        abort_unless(in_array($report->status, ['approved', 'released'], true), 422, 'Only approved reports can be released.');

        return $this->reportTransition($report, 'radiology.report_released', 'released', $actor);
    }

    public function communicateCritical(RadiologyReport $report, array $data, User $actor): RadiologyCriticalCommunication
    {
        abort_unless($report->has_critical_finding, 422, 'Only critical findings require communication.');
        $communication = RadiologyCriticalCommunication::create($data + ['hospital_id' => $report->hospital_id, 'radiology_report_id' => $report->id, 'communicated_by' => $actor->id, 'communicated_at' => now()]);
        $this->event($communication, 'radiology.critical_communicated', null, null, null, $communication->toArray(), $actor);

        return $communication;
    }

    public function acknowledgeCritical(RadiologyCriticalCommunication $communication, User $actor, string $notes): RadiologyCriticalCommunication
    {
        $before = $communication->toArray();
        $communication->forceFill(['acknowledged_by' => $actor->id, 'acknowledged_at' => now(), 'escalation_notes' => $notes])->save();
        $this->event($communication, 'radiology.critical_acknowledged', null, null, $before, $communication->fresh()->toArray(), $actor, $notes);

        return $communication->refresh();
    }

    public function amendReport(RadiologyReport $report, array $data, User $actor): RadiologyReportAmendment
    {
        abort_unless(in_array($report->status, ['approved', 'released'], true), 422, 'Only approved or released reports can be amended.');
        $amendment = RadiologyReportAmendment::create($data + ['hospital_id' => $report->hospital_id, 'radiology_report_id' => $report->id, 'authored_by' => $actor->id, 'authored_at' => now()]);
        $this->event($report, 'radiology.report_amended', $report->status, $report->status, null, $amendment->toArray(), $actor, $data['reason']);
        $this->activity->record($report->request->patient, 'radiology.report_amended', $actor, ['request_number' => $report->request->request_number]);

        return $amendment;
    }

    public function uploadAttachment(RadiologyRequest $request, UploadedFile $file, User $actor, ?RadiologyReport $report = null): RadiologyAttachment
    {
        $extension = strtolower($file->getClientOriginalExtension());
        abort_unless(in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true), 422, 'Unsupported attachment extension.');
        abort_unless(in_array($file->getMimeType(), ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true), 422, 'Unsupported attachment MIME type.');
        $storedName = (string) Str::uuid().'.'.$extension;
        $path = $file->storeAs('radiology/'.$request->id, $storedName, 'local');
        $attachment = RadiologyAttachment::create(['hospital_id' => $request->hospital_id, 'radiology_request_id' => $request->id, 'radiology_report_id' => $report?->id, 'uploaded_by' => $actor->id, 'disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'stored_name' => $storedName, 'mime_type' => $file->getMimeType(), 'extension' => $extension, 'size_bytes' => $file->getSize(), 'scan_status' => 'quarantined', 'status' => 'active']);
        $this->event($attachment, 'radiology.attachment_uploaded', null, 'quarantined', null, $attachment->toArray(), $actor);

        return $attachment;
    }

    public function clearAttachment(RadiologyAttachment $attachment, User $actor): RadiologyAttachment
    {
        $before = $attachment->toArray();
        $attachment->forceFill(['scan_status' => 'cleared', 'cleared_at' => now()])->save();
        $this->event($attachment, 'radiology.attachment_cleared', 'quarantined', 'cleared', $before, $attachment->fresh()->toArray(), $actor);

        return $attachment->refresh();
    }

    public function retireAttachment(RadiologyAttachment $attachment, User $actor, string $reason): RadiologyAttachment
    {
        abort_if($attachment->report?->status === 'released', 422, 'Released report attachments cannot be deleted; retire only before release.');
        $before = $attachment->toArray();
        $attachment->forceFill(['status' => 'retired', 'retired_by' => $actor->id, 'retired_at' => now(), 'retirement_reason' => $reason])->save();
        $this->event($attachment, 'radiology.attachment_retired', 'active', 'retired', $before, $attachment->fresh()->toArray(), $actor, $reason);

        return $attachment->refresh();
    }

    public function logAttachmentAccess(RadiologyAttachment $attachment, User $actor, string $action): void
    {
        $this->event($attachment, $action, $attachment->status, $attachment->status, null, null, $actor);
    }

    private function reportTransition(RadiologyReport $report, string $action, string $status, User $actor): RadiologyReport
    {
        return DB::transaction(function () use ($report, $action, $status, $actor): RadiologyReport {
            $report = RadiologyReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            $before = $report->toArray();
            $from = $report->status;
            $updates = ['status' => $status];
            if ($status === 'verified') {
                $updates += ['verified_by' => $actor->id, 'verified_at' => now()];
            }
            if ($status === 'approved') {
                $updates += ['approved_by' => $actor->id, 'approved_at' => now()];
            }
            if ($status === 'released') {
                $updates += ['released_by' => $actor->id, 'released_at' => now()];
            }
            $report->forceFill($updates)->save();
            $requestStatus = $status === 'released' ? 'released' : $status;
            $requestUpdates = ['status' => $requestStatus];
            if ($status === 'released') {
                $requestUpdates += ['released_by' => $actor->id, 'released_at' => now()];
            }
            $report->request->forceFill($requestUpdates)->save();
            if (in_array($status, ['approved', 'released'], true)) {
                $this->activity->record($report->request->patient, 'radiology.report_'.$status, $actor, ['request_number' => $report->request->request_number]);
            }
            $this->event($report, $action, $from, $status, $before, $report->fresh()->toArray(), $actor);

            return $report->refresh();
        });
    }

    private function assertStatus(RadiologyRequest $request, array $allowed, array $updates): array
    {
        abort_unless(in_array($request->status, $allowed, true), 422, 'Invalid radiology status transition.');

        return $updates;
    }

    private function invoiceFor(RadiologyRequest $request, Patient $patient, array $data, User $actor): Invoice
    {
        return $request->invoice ?: $this->invoices->createDraft(['facility_id' => $request->facility_id, 'visit_id' => $request->visit_id, 'clinical_encounter_id' => $request->clinical_encounter_id, 'currency' => $data['currency'] ?? 'NGN'], $patient, $actor);
    }

    private function allocate(int $hospitalId, string $key): string
    {
        return $this->numbers->allocate(NumberSequence::where('hospital_id', $hospitalId)->whereNull('facility_id')->where('key', $key)->where('status', 'active')->firstOrFail());
    }

    private function event(Model $subject, string $action, ?string $from, ?string $to, ?array $before, ?array $after, User $actor, ?string $reason = null): void
    {
        RadiologyEvent::create(['hospital_id' => $subject->hospital_id, 'subject_type' => $subject::class, 'subject_id' => $subject->getKey(), 'actor_id' => $actor->id, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'before' => $before, 'after' => $after, 'reason' => $reason, 'occurred_at' => now()]);
        $this->audit->record($action, $subject, $before, $after, actor: $actor, reason: $reason);
    }
}
