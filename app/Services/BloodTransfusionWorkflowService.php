<?php

namespace App\Services;

use App\Models\BloodComponentIssue;
use App\Models\BloodTransfusionEpisode;
use App\Models\BloodTransfusionObservation;
use App\Models\BloodTransfusionReaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BloodTransfusionWorkflowService
{
    public function start(BloodComponentIssue $issue, array $data, User $actor): BloodTransfusionEpisode
    {
        abort_unless($issue->hospital_id === $actor->hospitalId() || $actor->hasRole('superadmin'), 403);
        abort_unless($issue->status === 'issued', 422, 'Only an active issued component can begin transfusion.');
        abort_if($issue->transfusionEpisode()->exists(), 422, 'A transfusion episode already exists for this issued component.');

        $request = $issue->request;
        abort_unless($request && $request->patient_id === $issue->patient_id, 422, 'Issue and request patient identity do not match.');
        abort_unless(($data['identity_check_status'] ?? null) === 'matched', 422, 'Documented patient/component identity checks must match before transfusion starts.');
        abort_unless(trim((string) ($data['patient_identifier_checked'] ?? '')) !== '', 422, 'Patient identifier confirmation is required.');
        abort_unless(trim((string) ($data['component_identifier_checked'] ?? '')) !== '', 422, 'Component identifier confirmation is required.');

        return DB::transaction(function () use ($issue, $request, $data, $actor): BloodTransfusionEpisode {
            $episode = BloodTransfusionEpisode::create([
                'hospital_id' => $issue->hospital_id,
                'blood_component_issue_id' => $issue->id,
                'blood_request_id' => $issue->blood_request_id,
                'patient_id' => $issue->patient_id,
                'admission_id' => $request->admission_id,
                'clinical_encounter_id' => $request->clinical_encounter_id,
                'started_by' => $actor->id,
                'started_at' => $data['started_at'] ?? now(),
                'status' => 'in_progress',
                'destination' => $data['destination'] ?? $issue->destination,
                'patient_identifier_checked' => $data['patient_identifier_checked'],
                'component_identifier_checked' => $data['component_identifier_checked'],
                'identity_check_status' => 'matched',
                'notes' => $data['notes'] ?? null,
            ]);

            app(AuditService::class)->record('blood_transfusion.started', $episode, null, $episode->toArray(), actor: $actor);

            return $episode;
        });
    }

    public function observe(BloodTransfusionEpisode $episode, array $data, User $actor): BloodTransfusionObservation
    {
        $this->assertAccessible($episode, $actor);
        abort_unless($episode->status === 'in_progress', 422, 'Observations can only be added while transfusion is in progress.');

        $observation = $episode->observations()->create([
            'recorded_by' => $actor->id,
            'observed_at' => $data['observed_at'] ?? now(),
            'temperature_c' => $data['temperature_c'] ?? null,
            'pulse_bpm' => $data['pulse_bpm'] ?? null,
            'respiratory_rate' => $data['respiratory_rate'] ?? null,
            'systolic_bp' => $data['systolic_bp'] ?? null,
            'diastolic_bp' => $data['diastolic_bp'] ?? null,
            'spo2_percent' => $data['spo2_percent'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        app(AuditService::class)->record('blood_transfusion.observation_recorded', $observation, null, $observation->toArray(), actor: $actor);

        return $observation;
    }

    public function complete(BloodTransfusionEpisode $episode, array $data, User $actor): BloodTransfusionEpisode
    {
        $this->assertAccessible($episode, $actor);
        abort_unless($episode->status === 'in_progress', 422, 'Only an in-progress transfusion can be completed.');

        $before = $episode->toArray();
        $episode->update([
            'status' => 'completed',
            'completed_by' => $actor->id,
            'completed_at' => $data['completed_at'] ?? now(),
            'notes' => $this->appendNote($episode->notes, $data['notes'] ?? null),
        ]);
        app(AuditService::class)->record('blood_transfusion.completed', $episode, $before, $episode->fresh()->toArray(), actor: $actor);

        return $episode->fresh();
    }

    public function stop(BloodTransfusionEpisode $episode, array $data, User $actor): BloodTransfusionEpisode
    {
        $this->assertAccessible($episode, $actor);
        abort_unless($episode->status === 'in_progress', 422, 'Only an in-progress transfusion can be stopped.');

        $before = $episode->toArray();
        $episode->update([
            'status' => 'stopped',
            'stopped_by' => $actor->id,
            'stopped_at' => $data['stopped_at'] ?? now(),
            'stop_reason' => $data['stop_reason'],
            'notes' => $this->appendNote($episode->notes, $data['notes'] ?? null),
        ]);
        app(AuditService::class)->record('blood_transfusion.stopped', $episode, $before, $episode->fresh()->toArray(), actor: $actor);

        return $episode->fresh();
    }

    public function reportReaction(BloodTransfusionEpisode $episode, array $data, User $actor): BloodTransfusionReaction
    {
        $this->assertAccessible($episode, $actor);
        abort_unless(in_array($episode->status, ['in_progress', 'stopped'], true), 422, 'A reaction can only be recorded against an active or stopped transfusion episode.');

        return DB::transaction(function () use ($episode, $data, $actor): BloodTransfusionReaction {
            if ($episode->status === 'in_progress') {
                $episode->update([
                    'status' => 'stopped',
                    'stopped_by' => $actor->id,
                    'stopped_at' => $data['occurred_at'] ?? now(),
                    'stop_reason' => 'Transfusion reaction reported',
                ]);
            }

            $reaction = $episode->reactions()->create([
                'reported_by' => $actor->id,
                'occurred_at' => $data['occurred_at'] ?? now(),
                'reported_severity' => $data['reported_severity'] ?? null,
                'observed_signs' => $data['observed_signs'],
                'immediate_actions' => $data['immediate_actions'] ?? null,
                'clinician_notified_at' => $data['clinician_notified_at'] ?? null,
                'blood_bank_notified_at' => $data['blood_bank_notified_at'] ?? null,
                'status' => 'open',
            ]);

            app(AuditService::class)->record('blood_transfusion.reaction_reported', $reaction, null, $reaction->toArray(), actor: $actor);

            return $reaction;
        });
    }

    public function resolveReaction(BloodTransfusionReaction $reaction, string $notes, User $actor): BloodTransfusionReaction
    {
        $reaction->loadMissing('episode');
        $this->assertAccessible($reaction->episode, $actor);
        abort_unless($reaction->status === 'open', 422, 'Only an open reaction record can be resolved.');

        $before = $reaction->toArray();
        $reaction->update([
            'status' => 'resolved',
            'resolved_by' => $actor->id,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);
        app(AuditService::class)->record('blood_transfusion.reaction_resolved', $reaction, $before, $reaction->fresh()->toArray(), actor: $actor);

        return $reaction->fresh();
    }

    private function assertAccessible(BloodTransfusionEpisode $episode, User $actor): void
    {
        abort_unless($episode->hospital_id === $actor->hospitalId() || $actor->hasRole('superadmin'), 403);
    }

    private function appendNote(?string $existing, ?string $note): ?string
    {
        if (blank($note)) return $existing;
        if (blank($existing)) return $note;

        return $existing."\n".$note;
    }
}
