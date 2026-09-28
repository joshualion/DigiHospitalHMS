<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdmissionIntegrityService
{
    public function bedBoard(int $hospitalId): array
    {
        $activeAdmissions = Admission::with('patient:id,hospital_number,first_name,middle_name,last_name')
            ->where('hospital_id', $hospitalId)
            ->whereIn('status', ['admitted', 'transferred'])
            ->whereNotNull('current_bed_id')
            ->get()
            ->keyBy('current_bed_id');

        $beds = Bed::with(['ward', 'room', 'bedClass.billableService'])
            ->where('hospital_id', $hospitalId)
            ->orderBy('label')
            ->get()
            ->map(function (Bed $bed) use ($activeAdmissions): Bed {
                $admission = $activeAdmissions->get($bed->id);
                $effectiveState = $admission ? 'occupied' : ($bed->state === 'occupied' ? 'available' : $bed->state);

                $bed->setAttribute('effective_state', $effectiveState);
                $bed->setAttribute('integrity_issue',
                    $admission && $bed->state !== 'occupied'
                        ? 'Active admission exists but stored bed state is not occupied.'
                        : (! $admission && $bed->state === 'occupied'
                            ? 'Bed is marked occupied but no active admission is assigned.'
                            : null)
                );
                $bed->setRelation('activeAdmission', $admission);

                return $bed;
            });

        $census = $beds
            ->groupBy(fn (Bed $bed) => $bed->getAttribute('effective_state'))
            ->map(fn (Collection $group, string $state) => ['state' => $state, 'count' => $group->count()])
            ->values()
            ->all();

        return [
            'beds' => $beds,
            'census' => $census,
            'issues' => $beds->filter(fn (Bed $bed) => filled($bed->getAttribute('integrity_issue')))->values(),
        ];
    }

    public function reconcile(Hospital $hospital, User $actor, AuditService $audit): array
    {
        return DB::transaction(function () use ($hospital, $actor, $audit): array {
            $activeBedIds = Admission::where('hospital_id', $hospital->id)
                ->whereIn('status', ['admitted', 'transferred'])
                ->whereNotNull('current_bed_id')
                ->pluck('current_bed_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $changed = [];

            Bed::where('hospital_id', $hospital->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->each(function (Bed $bed) use ($activeBedIds, $actor, $audit, &$changed): void {
                    $shouldBeOccupied = in_array($bed->id, $activeBedIds, true);

                    if ($shouldBeOccupied && $bed->state !== 'occupied') {
                        $before = $bed->only(['state', 'state_reason']);
                        $bed->update(['state' => 'occupied', 'state_reason' => 'Reconciled from active admission']);
                        $audit->record('admissions.bed_reconciled', $bed, $before, $bed->only(['state', 'state_reason']), actor: $actor, reason: 'Active admission requires occupied state');
                        $changed[] = $bed->id;
                    }

                    if (! $shouldBeOccupied && $bed->state === 'occupied') {
                        $before = $bed->only(['state', 'state_reason']);
                        $bed->update(['state' => 'available', 'state_reason' => 'Reconciled: no active admission assigned']);
                        $audit->record('admissions.bed_reconciled', $bed, $before, $bed->only(['state', 'state_reason']), actor: $actor, reason: 'No active admission assigned');
                        $changed[] = $bed->id;
                    }
                });

            return ['changed_bed_ids' => $changed, 'changed_count' => count($changed)];
        });
    }
}
