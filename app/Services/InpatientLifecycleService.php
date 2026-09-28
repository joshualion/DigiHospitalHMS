<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\EmarSchedule;
use App\Models\Hospital;
use App\Models\InpatientChart;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InpatientLifecycleService
{
    public function closeForAdmission(Admission $admission, User $actor, AuditService $audit): array
    {
        return DB::transaction(function () use ($admission, $actor, $audit): array {
            $chart = InpatientChart::where('admission_id', $admission->id)->lockForUpdate()->first();

            if (! $chart) {
                return ['chart_closed' => false, 'emar_retired' => 0];
            }

            $chartClosed = false;

            if ($chart->status === 'active') {
                $before = $chart->only(['status', 'closed_by', 'closed_at']);
                $chart->forceFill([
                    'status' => 'closed',
                    'closed_by' => $actor->id,
                    'closed_at' => $admission->discharged_at ?? now(),
                ])->save();

                $audit->record(
                    'inpatient.chart_closed_on_discharge',
                    $chart,
                    $before,
                    $chart->only(['status', 'closed_by', 'closed_at']),
                    actor: $actor,
                    reason: 'Admission discharged'
                );
                $chartClosed = true;
            }

            $retired = EmarSchedule::where('inpatient_chart_id', $chart->id)
                ->whereIn('status', ['pending', 'delayed', 'prn_available'])
                ->update(['status' => 'cancelled']);

            if ($retired > 0) {
                $audit->record(
                    'emar.pending_schedules_retired_on_discharge',
                    $chart,
                    null,
                    ['retired_count' => $retired],
                    actor: $actor,
                    reason: 'Admission discharged'
                );
            }

            return ['chart_closed' => $chartClosed, 'emar_retired' => $retired];
        });
    }

    public function reconcile(Hospital $hospital, User $actor, AuditService $audit): array
    {
        $closedCharts = 0;
        $retiredSchedules = 0;

        $charts = InpatientChart::with('admission')
            ->where('hospital_id', $hospital->id)
            ->where('status', 'active')
            ->get();

        foreach ($charts as $chart) {
            $admission = $chart->admission;

            if (! $admission || ! in_array($admission->status, ['admitted', 'transferred'], true) || $admission->discharged_at) {
                $result = DB::transaction(function () use ($chart, $admission, $actor, $audit): array {
                    $locked = InpatientChart::whereKey($chart->id)->lockForUpdate()->firstOrFail();

                    if ($locked->status !== 'active') {
                        return ['chart_closed' => false, 'emar_retired' => 0];
                    }

                    $before = $locked->only(['status', 'closed_by', 'closed_at']);
                    $locked->forceFill([
                        'status' => 'closed',
                        'closed_by' => $actor->id,
                        'closed_at' => $admission?->discharged_at ?? now(),
                    ])->save();

                    $retired = EmarSchedule::where('inpatient_chart_id', $locked->id)
                        ->whereIn('status', ['pending', 'delayed', 'prn_available'])
                        ->update(['status' => 'cancelled']);

                    $audit->record(
                        'inpatient.chart_reconciled',
                        $locked,
                        $before,
                        $locked->only(['status', 'closed_by', 'closed_at']),
                        actor: $actor,
                        reason: 'Chart no longer has an active admission'
                    );

                    return ['chart_closed' => true, 'emar_retired' => $retired];
                });

                $closedCharts += $result['chart_closed'] ? 1 : 0;
                $retiredSchedules += $result['emar_retired'];
            }
        }

        return [
            'closed_charts' => $closedCharts,
            'retired_schedules' => $retiredSchedules,
        ];
    }
}
