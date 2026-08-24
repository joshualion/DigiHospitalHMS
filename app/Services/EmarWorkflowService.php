<?php

namespace App\Services;

use App\Models\EmarAdministration;
use App\Models\EmarAmendment;
use App\Models\EmarEvent;
use App\Models\EmarSchedule;
use App\Models\InpatientChart;
use App\Models\Prescription;
use App\Models\PrescriptionDispense;
use App\Models\PrescriptionItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmarWorkflowService
{
    private const ADMINISTERED_OUTCOMES = ['administered'];

    private const NON_ADMINISTERED_OUTCOMES = ['omitted', 'refused', 'held', 'unavailable', 'delayed', 'not-given'];

    public function __construct(
        private readonly AuditService $audit,
        private readonly PatientActivity $activity,
    ) {}

    public function syncSchedules(InpatientChart $chart, User $actor): Collection
    {
        $this->assertActiveAdmission($chart);

        return DB::transaction(function () use ($chart, $actor): Collection {
            $created = collect();
            $prescriptions = Prescription::with(['items.dispenses.batch', 'reviews'])
                ->where('hospital_id', $chart->hospital_id)
                ->where('patient_id', $chart->patient_id)
                ->whereIn('status', ['signed', 'completed'])
                ->whereHas('reviews', fn ($query) => $query->whereIn('action', ['approved', 'substitution_authorized']))
                ->get();

            foreach ($prescriptions as $prescription) {
                foreach ($prescription->items as $item) {
                    if (! $this->itemEligible($item) || bccomp($this->remainingDispensedQuantity($item), '0', 4) <= 0) {
                        continue;
                    }
                    foreach ($this->scheduleTimes($item) as $scheduledAt) {
                        $schedule = EmarSchedule::firstOrCreate(
                            ['prescription_item_id' => $item->id, 'scheduled_at' => $scheduledAt],
                            $this->schedulePayload($chart, $prescription, $item, $scheduledAt),
                        );
                        if ($schedule->wasRecentlyCreated) {
                            $created->push($schedule);
                            $this->event($schedule, 'emar.schedule_created', null, $schedule->toArray(), $actor);
                        }
                    }
                }
            }

            return $created;
        });
    }

    public function administer(EmarSchedule $schedule, array $data, User $actor): EmarAdministration
    {
        return DB::transaction(function () use ($schedule, $data, $actor): EmarAdministration {
            $schedule = EmarSchedule::whereKey($schedule->id)->lockForUpdate()->firstOrFail();
            abort_if($schedule->administration()->exists(), 422, 'This scheduled dose already has an administration record.');
            $chart = InpatientChart::whereKey($schedule->inpatient_chart_id)->lockForUpdate()->firstOrFail();
            $this->assertActiveAdmission($chart);

            $item = PrescriptionItem::with(['prescription', 'dispenses.batch'])->whereKey($schedule->prescription_item_id)->lockForUpdate()->firstOrFail();
            $this->assertPrescriptionAdministerable($item, $data['actual_at'] ?? now());

            $outcome = $data['outcome'];
            abort_unless(in_array($outcome, array_merge(self::ADMINISTERED_OUTCOMES, self::NON_ADMINISTERED_OUTCOMES), true), 422, 'Invalid eMAR outcome.');
            if (in_array($outcome, self::NON_ADMINISTERED_OUTCOMES, true)) {
                abort_unless(filled($data['reason'] ?? null), 422, 'A reason is required for this outcome.');
            }
            if ($schedule->is_prn) {
                abort_unless(filled($data['prn_indication'] ?? null), 422, 'PRN indication is required.');
            }

            $confirmation = $data['confirmation'] ?? [];
            foreach (['patient', 'medication', 'dose', 'route', 'timing'] as $key) {
                abort_unless(($confirmation[$key] ?? false) === true, 422, 'All pre-administration confirmations are required.');
            }

            $dispense = null;
            $quantity = (string) ($data['quantity_administered'] ?? 1);
            if ($outcome === 'administered') {
                abort_unless(bccomp($this->remainingDispensedQuantity($item), $quantity, 4) >= 0, 422, 'Dispensed quantity is insufficient.');
                $dispense = isset($data['prescription_dispense_id'])
                    ? PrescriptionDispense::where('prescription_item_id', $item->id)->where('action', 'dispense')->findOrFail($data['prescription_dispense_id'])
                    : $this->firstDispenseWithRemaining($item);
                abort_unless($dispense, 422, 'A dispensed batch is required for administration.');
                abort_unless(bccomp($this->remainingDispenseQuantity($dispense), $quantity, 4) >= 0, 422, 'Selected dispense has insufficient remaining quantity.');
            }

            $administration = EmarAdministration::create([
                'hospital_id' => $schedule->hospital_id,
                'facility_id' => $schedule->facility_id,
                'inpatient_chart_id' => $schedule->inpatient_chart_id,
                'admission_id' => $schedule->admission_id,
                'patient_id' => $schedule->patient_id,
                'emar_schedule_id' => $schedule->id,
                'prescription_id' => $schedule->prescription_id,
                'prescription_item_id' => $schedule->prescription_item_id,
                'prescription_dispense_id' => $dispense?->id,
                'inventory_batch_id' => $dispense?->inventory_batch_id,
                'medicine_name' => $schedule->medicine_name,
                'dose' => $schedule->dose,
                'route' => $schedule->route,
                'scheduled_at' => $schedule->scheduled_at,
                'actual_at' => $data['actual_at'] ?? now(),
                'quantity_administered' => $outcome === 'administered' ? $quantity : 0,
                'outcome' => $outcome,
                'confirmation' => $confirmation,
                'reason' => $data['reason'] ?? null,
                'prn_indication' => $data['prn_indication'] ?? null,
                'prn_response' => $data['prn_response'] ?? null,
                'administered_by' => $actor->id,
            ]);
            $schedule->forceFill(['status' => $outcome])->save();
            $this->event($administration, 'emar.administration_recorded', null, $administration->toArray(), $actor, $data['reason'] ?? null);
            $this->activity->record($chart->patient, 'emar.administration_recorded', $actor, ['admission_id' => $chart->admission_id, 'administration_id' => $administration->id, 'outcome' => $outcome]);

            return $administration->refresh();
        });
    }

    public function amend(EmarAdministration $administration, array $data, User $actor): EmarAmendment
    {
        $amendment = EmarAmendment::create([
            'hospital_id' => $administration->hospital_id,
            'emar_administration_id' => $administration->id,
            'reason' => $data['reason'],
            'content' => $data['content'],
            'authored_by' => $actor->id,
            'authored_at' => now(),
        ]);
        $this->event($administration, 'emar.amended', null, $amendment->toArray(), $actor, $data['reason']);

        return $amendment;
    }

    public function remainingDispensedQuantity(PrescriptionItem $item): string
    {
        $administered = EmarAdministration::where('prescription_item_id', $item->id)->where('outcome', 'administered')->sum('quantity_administered');

        return bcsub((string) $item->dispensed_quantity, (string) $administered, 4);
    }

    private function itemEligible(PrescriptionItem $item): bool
    {
        return ! in_array($item->status, ['discontinued', 'cancelled', 'completed'], true)
            && ! ($item->end_at && $item->end_at->isPast());
    }

    private function assertActiveAdmission(InpatientChart $chart): void
    {
        $chart->loadMissing('admission');
        abort_unless($chart->status === 'active', 422, 'The inpatient chart is closed.');
        abort_unless(in_array($chart->admission->status, ['admitted', 'transferred'], true), 422, 'An active inpatient admission is required.');
        abort_if($chart->admission->discharged_at, 422, 'Medication cannot be administered after discharge.');
    }

    private function assertPrescriptionAdministerable(PrescriptionItem $item, mixed $actualAt): void
    {
        abort_unless(in_array($item->prescription->status, ['signed', 'completed'], true), 422, 'Only signed, reviewed and dispensed prescriptions can be administered.');
        abort_unless($item->prescription->reviews()->whereIn('action', ['approved', 'substitution_authorized'])->exists(), 422, 'Pharmacist approval is required.');
        abort_if(in_array($item->status, ['discontinued', 'cancelled', 'completed'], true), 422, 'Medication order is no longer active.');
        abort_if($item->end_at && $item->end_at->lt($actualAt), 422, 'Medication order has expired.');
    }

    private function scheduleTimes(PrescriptionItem $item): array
    {
        $orderType = $item->medication_order_type ?: ($item->is_prn ? 'prn' : 'regular');
        if ($orderType === 'prn') {
            return [null];
        }

        $start = CarbonImmutable::parse($item->start_at ?? now());
        if (in_array($orderType, ['once', 'stat'], true)) {
            return [$start];
        }

        $times = collect($item->scheduled_times ?: [now()->format('H:i')])->filter()->values();

        return $times->map(fn (string $time): CarbonImmutable => CarbonImmutable::parse($start->toDateString().' '.$time))
            ->filter(fn (CarbonImmutable $scheduled): bool => ! $item->end_at || $scheduled->lte($item->end_at))
            ->values()
            ->all();
    }

    private function schedulePayload(InpatientChart $chart, Prescription $prescription, PrescriptionItem $item, mixed $scheduledAt): array
    {
        $orderType = $item->medication_order_type ?: ($item->is_prn ? 'prn' : 'regular');

        return [
            'hospital_id' => $chart->hospital_id,
            'facility_id' => $chart->facility_id,
            'inpatient_chart_id' => $chart->id,
            'admission_id' => $chart->admission_id,
            'patient_id' => $chart->patient_id,
            'prescription_id' => $prescription->id,
            'medicine_name' => $item->medicine_name,
            'dose' => $item->dose,
            'route' => $item->route,
            'frequency' => $item->frequency,
            'order_type' => $orderType,
            'is_prn' => $orderType === 'prn' || $item->is_prn,
            'prn_instructions' => $item->prn_instructions,
            'status' => $orderType === 'prn' ? 'prn_available' : 'pending',
        ];
    }

    private function firstDispenseWithRemaining(PrescriptionItem $item): ?PrescriptionDispense
    {
        return $item->dispenses()->where('action', 'dispense')->oldest()->get()->first(fn (PrescriptionDispense $dispense): bool => bccomp($this->remainingDispenseQuantity($dispense), '0', 4) > 0);
    }

    private function remainingDispenseQuantity(PrescriptionDispense $dispense): string
    {
        $administered = EmarAdministration::where('prescription_dispense_id', $dispense->id)->where('outcome', 'administered')->sum('quantity_administered');

        return bcsub((string) $dispense->quantity, (string) $administered, 4);
    }

    private function event(Model $subject, string $action, ?array $before, ?array $after, User $actor, ?string $reason = null): void
    {
        EmarEvent::create(['hospital_id' => $subject->hospital_id, 'subject_type' => $subject::class, 'subject_id' => $subject->getKey(), 'actor_id' => $actor->id, 'action' => $action, 'before' => $before, 'after' => $after, 'reason' => $reason, 'occurred_at' => now()]);
        $this->audit->record($action, $subject, $before, $after, actor: $actor, reason: $reason);
    }
}
