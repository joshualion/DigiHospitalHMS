<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\InpatientAmendment;
use App\Models\InpatientCarePlan;
use App\Models\InpatientChart;
use App\Models\InpatientChartEvent;
use App\Models\InpatientDiagnosis;
use App\Models\InpatientDischargeSummary;
use App\Models\InpatientHandoverRecord;
use App\Models\InpatientIntakeOutput;
use App\Models\InpatientNursingNote;
use App\Models\InpatientObservation;
use App\Models\InpatientOrder;
use App\Models\InpatientProgressNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class InpatientChartWorkflowService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly PatientActivity $activity,
    ) {}

    public function chartForAdmission(Admission $admission, User $actor): InpatientChart
    {
        abort_unless(in_array($admission->status, ['admitted', 'transferred'], true), 422, 'An active admission is required.');

        return DB::transaction(function () use ($admission, $actor): InpatientChart {
            $admission = Admission::whereKey($admission->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($admission->status, ['admitted', 'transferred'], true), 422, 'An active admission is required.');

            $chart = InpatientChart::firstOrCreate(
                ['admission_id' => $admission->id],
                [
                    'hospital_id' => $admission->hospital_id,
                    'facility_id' => $admission->facility_id,
                    'patient_id' => $admission->patient_id,
                    'visit_id' => $admission->visit_id,
                    'clinical_encounter_id' => $admission->clinical_encounter_id,
                    'department_id' => $admission->department_id,
                    'ward_id' => $admission->current_ward_id,
                    'bed_id' => $admission->current_bed_id,
                    'status' => 'active',
                    'opened_by' => $actor->id,
                    'opened_at' => now(),
                ],
            );

            if ($chart->wasRecentlyCreated) {
                $this->event($chart, 'inpatient.chart_opened', null, $chart->toArray(), $actor);
                $this->activity->record($admission->patient, 'inpatient.chart_opened', $actor, ['admission_id' => $admission->id, 'chart_id' => $chart->id]);
            } else {
                $chart->forceFill([
                    'facility_id' => $admission->facility_id,
                    'department_id' => $admission->department_id,
                    'ward_id' => $admission->current_ward_id,
                    'bed_id' => $admission->current_bed_id,
                ])->save();
            }

            return $chart->refresh();
        });
    }

    public function progressNote(InpatientChart $chart, array $data, User $actor): InpatientProgressNote
    {
        $this->assertActive($chart);
        $note = InpatientProgressNote::create($this->base($chart) + [
            'note_type' => $data['note_type'] ?? 'soap',
            'subjective' => $data['subjective'] ?? null,
            'objective' => $data['objective'] ?? null,
            'assessment' => $data['assessment'] ?? null,
            'plan' => $data['plan'] ?? null,
            'narrative' => $data['narrative'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'authored_by' => $actor->id,
            'authored_at' => now(),
        ]);
        $this->event($chart, 'inpatient.progress_note_created', null, $note->toArray(), $actor);
        $this->activity->record($chart->patient, 'inpatient.progress_note_created', $actor, ['chart_id' => $chart->id, 'note_id' => $note->id]);

        return $note;
    }

    public function signProgressNote(InpatientProgressNote $note, User $actor): InpatientProgressNote
    {
        abort_unless($note->status === 'draft', 422, 'Only draft progress notes can be signed.');
        $before = $note->toArray();
        $note->forceFill(['status' => 'signed', 'signed_by' => $actor->id, 'signed_at' => now()])->save();
        $this->event($note->chart, 'inpatient.progress_note_signed', $before, $note->fresh()->toArray(), $actor);

        return $note->refresh();
    }

    public function amend(Model $subject, array $data, User $actor): InpatientAmendment
    {
        abort_unless(method_exists($subject, 'isSigned') && $subject->isSigned(), 422, 'Only signed records require append-only amendments.');
        $chart = $subject->chart;
        $amendment = InpatientAmendment::create([
            'hospital_id' => $chart->hospital_id,
            'inpatient_chart_id' => $chart->id,
            'amendable_type' => $subject::class,
            'amendable_id' => $subject->getKey(),
            'reason' => $data['reason'],
            'content' => $data['content'],
            'authored_by' => $actor->id,
            'authored_at' => now(),
        ]);
        $this->event($chart, 'inpatient.amended', null, $amendment->toArray(), $actor, $data['reason']);
        $this->activity->record($chart->patient, 'inpatient.amended', $actor, ['chart_id' => $chart->id, 'subject_id' => $subject->getKey()]);

        return $amendment;
    }

    public function nursingNote(InpatientChart $chart, array $data, User $actor): InpatientNursingNote
    {
        $this->assertActive($chart);
        $note = InpatientNursingNote::create($this->base($chart) + ['shift' => $data['shift'] ?? null, 'note' => $data['note'], 'status' => 'signed', 'authored_by' => $actor->id, 'authored_at' => now()]);
        $this->event($chart, 'inpatient.nursing_note_recorded', null, $note->toArray(), $actor);

        return $note;
    }

    public function observation(InpatientChart $chart, array $data, User $actor): InpatientObservation
    {
        $this->assertActive($chart);
        $observation = InpatientObservation::create($this->base($chart) + $data + ['recorded_by' => $actor->id]);
        $this->event($chart, 'inpatient.observation_recorded', null, $observation->toArray(), $actor);
        $this->activity->record($chart->patient, 'inpatient.observation_recorded', $actor, ['chart_id' => $chart->id]);

        return $observation;
    }

    public function intakeOutput(InpatientChart $chart, array $data, User $actor): InpatientIntakeOutput
    {
        $this->assertActive($chart);
        $record = InpatientIntakeOutput::create($this->base($chart) + $data + ['recorded_by' => $actor->id]);
        $this->event($chart, 'inpatient.intake_output_recorded', null, $record->toArray(), $actor);

        return $record;
    }

    public function carePlan(InpatientChart $chart, array $data, User $actor): InpatientCarePlan
    {
        $this->assertActive($chart);
        $plan = InpatientCarePlan::create($this->base($chart) + $data + ['recorded_by' => $actor->id, 'recorded_at' => now()]);
        $this->event($chart, 'inpatient.care_plan_recorded', null, $plan->toArray(), $actor);

        return $plan;
    }

    public function diagnosis(InpatientChart $chart, array $data, User $actor): InpatientDiagnosis
    {
        $this->assertActive($chart);
        $diagnosis = InpatientDiagnosis::create($this->base($chart) + $data + ['recorded_by' => $actor->id, 'recorded_at' => now()]);
        $this->event($chart, 'inpatient.diagnosis_recorded', null, $diagnosis->toArray(), $actor);

        return $diagnosis;
    }

    public function order(InpatientChart $chart, array $data, User $actor): InpatientOrder
    {
        $this->assertActive($chart);
        $order = InpatientOrder::create($this->base($chart) + [
            'order_type' => $data['order_type'],
            'instruction' => $data['instruction'],
            'status' => $data['status'] ?? 'draft',
            'ordered_by' => $actor->id,
            'ordered_at' => now(),
        ]);
        $this->event($chart, 'inpatient.order_created', null, $order->toArray(), $actor);

        return $order;
    }

    public function transitionOrder(InpatientOrder $order, string $action, User $actor, ?string $reason = null): InpatientOrder
    {
        return DB::transaction(function () use ($order, $action, $actor, $reason): InpatientOrder {
            $order = InpatientOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $before = $order->toArray();
            $updates = match ($action) {
                'activate' => $this->assertOrder($order, ['draft'], ['status' => 'active']),
                'acknowledge' => $this->assertOrder($order, ['active'], ['status' => 'acknowledged', 'acknowledged_by' => $actor->id, 'acknowledged_at' => now()]),
                'complete' => $this->assertOrder($order, ['active', 'acknowledged'], ['status' => 'completed', 'completed_by' => $actor->id, 'completed_at' => now()]),
                'discontinue' => $this->assertOrder($order, ['active', 'acknowledged'], ['status' => 'discontinued', 'status_reason' => $reason]),
                'cancel' => $this->assertOrder($order, ['draft', 'active'], ['status' => 'cancelled', 'status_reason' => $reason]),
                default => abort(422, 'Unsupported order transition.'),
            };
            $order->forceFill($updates)->save();
            $this->event($order->chart, "inpatient.order_{$action}", $before, $order->fresh()->toArray(), $actor, $reason);

            return $order->refresh();
        });
    }

    public function handover(InpatientChart $chart, array $data, User $actor): InpatientHandoverRecord
    {
        $this->assertActive($chart);
        $handover = InpatientHandoverRecord::create($this->base($chart) + $data + ['status' => 'signed', 'authored_by' => $actor->id, 'authored_at' => now()]);
        $this->event($chart, 'inpatient.handover_signed', null, $handover->toArray(), $actor);

        return $handover;
    }

    public function acknowledgeHandover(InpatientHandoverRecord $handover, User $actor): InpatientHandoverRecord
    {
        abort_if($handover->acknowledged_at, 422, 'Handover already acknowledged.');
        $before = $handover->toArray();
        $handover->forceFill(['status' => 'acknowledged', 'acknowledged_by' => $actor->id, 'acknowledged_at' => now()])->save();
        $this->event($handover->chart, 'inpatient.handover_acknowledged', $before, $handover->fresh()->toArray(), $actor);

        return $handover->refresh();
    }

    public function dischargeSummary(InpatientChart $chart, array $data, User $actor): InpatientDischargeSummary
    {
        $this->assertActive($chart);
        $defaults = $this->dischargeDefaults($chart);
        $summary = InpatientDischargeSummary::updateOrCreate(
            ['inpatient_chart_id' => $chart->id],
            $this->base($chart) + $defaults + $data + ['status' => 'draft', 'drafted_by' => $actor->id, 'drafted_at' => now()],
        );
        $this->event($chart, 'inpatient.discharge_summary_drafted', null, $summary->toArray(), $actor);

        return $summary->refresh();
    }

    public function signDischargeSummary(InpatientDischargeSummary $summary, User $actor): InpatientDischargeSummary
    {
        abort_unless($summary->status === 'draft', 422, 'Only draft discharge summaries can be signed.');
        $before = $summary->toArray();
        $summary->forceFill(['status' => 'signed', 'signed_by' => $actor->id, 'signed_at' => now()])->save();
        $summary->chart->forceFill(['status' => 'closed', 'closed_by' => $actor->id, 'closed_at' => now()])->save();
        $this->event($summary->chart, 'inpatient.discharge_summary_signed', $before, $summary->fresh()->toArray(), $actor);
        $this->activity->record($summary->chart->patient, 'inpatient.discharge_summary_signed', $actor, ['chart_id' => $summary->inpatient_chart_id]);

        return $summary->refresh();
    }

    private function assertActive(InpatientChart $chart): void
    {
        abort_unless($chart->status === 'active', 422, 'The inpatient chart is closed.');
        abort_unless(in_array($chart->admission->status, ['admitted', 'transferred'], true), 422, 'An active admission is required.');
    }

    private function assertOrder(InpatientOrder $order, array $allowed, array $updates): array
    {
        abort_unless(in_array($order->status, $allowed, true), 422, 'Invalid order transition.');

        return $updates;
    }

    private function base(InpatientChart $chart): array
    {
        return ['hospital_id' => $chart->hospital_id, 'inpatient_chart_id' => $chart->id, 'admission_id' => $chart->admission_id, 'patient_id' => $chart->patient_id];
    }

    private function dischargeDefaults(InpatientChart $chart): array
    {
        $chart->loadMissing(['admission', 'diagnoses', 'progressNotes', 'observations']);

        return [
            'admission_summary' => trim(implode("\n", array_filter([$chart->admission->reason, $chart->admission->provisional_diagnosis]))),
            'diagnosis_summary' => $chart->diagnoses->pluck('description')->implode("\n"),
            'results_summary' => 'Review laboratory and radiology reports in the patient timeline.',
            'clinical_course' => $chart->progressNotes->where('status', 'signed')->pluck('assessment')->filter()->implode("\n"),
            'discharge_plan' => $chart->admission->discharge_notes,
        ];
    }

    private function event(InpatientChart $chart, string $action, ?array $before, ?array $after, User $actor, ?string $reason = null): void
    {
        InpatientChartEvent::create(['hospital_id' => $chart->hospital_id, 'inpatient_chart_id' => $chart->id, 'subject_type' => $chart::class, 'subject_id' => $chart->id, 'actor_id' => $actor->id, 'action' => $action, 'before' => $before, 'after' => $after, 'reason' => $reason, 'occurred_at' => now()]);
        $this->audit->record($action, $chart, $before, $after, actor: $actor, reason: $reason);
    }
}
