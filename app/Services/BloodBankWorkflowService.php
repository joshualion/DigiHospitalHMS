<?php

namespace App\Services;

use App\Models\BloodBankAmendment;
use App\Models\BloodBankEvent;
use App\Models\BloodBankLocation;
use App\Models\BloodComponent;
use App\Models\BloodComponentTransfer;
use App\Models\BloodComponentType;
use App\Models\BloodDonation;
use App\Models\BloodDonationAppointment;
use App\Models\BloodDonor;
use App\Models\BloodDonorDeferral;
use App\Models\BloodDonorScreening;
use App\Models\BloodGroupResult;
use App\Models\BloodScreeningResult;
use App\Models\BloodScreeningTest;
use App\Models\BloodStorageUnit;
use App\Models\NumberSequence;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BloodBankWorkflowService
{
    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly AuditService $audit,
    ) {}

    public function registerDonor(array $data, User $actor): BloodDonor
    {
        return DB::transaction(function () use ($data, $actor): BloodDonor {
            $donor = new BloodDonor($data + [
                'registered_by' => $actor->id,
                'donor_number' => $this->allocate($data['hospital_id'], 'blood_donor_number'),
                'status' => 'active',
            ]);
            foreach (['address', 'phone', 'email', 'identifier_value'] as $field) {
                if (array_key_exists($field, $data)) {
                    $donor->{$field} = $data[$field];
                }
            }
            $donor->save();
            $this->event($donor, 'blood_bank.donor_registered', null, $donor->toArray(), $actor);

            return $donor->refresh();
        });
    }

    public function recordScreeningDecision(BloodDonor $donor, array $data, User $actor): BloodDonorScreening
    {
        abort_unless(in_array($data['eligibility_status'], ['eligible', 'deferred', 'ineligible'], true), 422, 'Eligibility decision must be manually recorded.');

        return DB::transaction(function () use ($donor, $data, $actor): BloodDonorScreening {
            $screening = BloodDonorScreening::create([
                'hospital_id' => $donor->hospital_id,
                'blood_donor_id' => $donor->id,
                'recorded_by' => $actor->id,
                'responses' => $data['responses'] ?? [],
                'eligibility_status' => $data['eligibility_status'],
                'decision_reason' => $data['decision_reason'],
                'decided_at' => now(),
            ]);

            $before = $donor->toArray();
            $donor->forceFill(['status' => $data['eligibility_status'] === 'eligible' ? 'active' : 'deferred'])->save();
            if ($data['eligibility_status'] !== 'eligible') {
                BloodDonorDeferral::create([
                    'hospital_id' => $donor->hospital_id,
                    'blood_donor_id' => $donor->id,
                    'recorded_by' => $actor->id,
                    'deferral_type' => $data['eligibility_status'],
                    'reason' => $data['decision_reason'],
                    'deferred_until' => $data['deferred_until'] ?? null,
                    'recorded_at' => now(),
                ]);
            }
            $this->event($donor, 'blood_bank.donor_eligibility_recorded', $before, $donor->fresh()->toArray(), $actor, $data['decision_reason']);
            $this->event($screening, 'blood_bank.donor_screening_recorded', null, $screening->toArray(), $actor, $data['decision_reason']);

            return $screening;
        });
    }

    public function scheduleAppointment(array $data, User $actor): BloodDonationAppointment
    {
        $appointment = BloodDonationAppointment::create($data + ['created_by' => $actor->id, 'status' => 'scheduled']);
        $this->event($appointment, 'blood_bank.donation_appointment_scheduled', null, $appointment->toArray(), $actor);

        return $appointment;
    }

    public function collect(array $data, User $actor): BloodDonation
    {
        return DB::transaction(function () use ($data, $actor): BloodDonation {
            $donor = BloodDonor::where('hospital_id', $data['hospital_id'])->lockForUpdate()->findOrFail($data['blood_donor_id']);
            abort_unless($donor->status === 'active', 422, 'Only manually eligible active donors can proceed to collection.');
            abort_unless($donor->screenings()->where('eligibility_status', 'eligible')->exists(), 422, 'A manual eligible donor decision is required before collection.');

            $donation = BloodDonation::create([
                'hospital_id' => $data['hospital_id'],
                'facility_id' => $data['facility_id'],
                'blood_donor_id' => $donor->id,
                'blood_donation_appointment_id' => $data['blood_donation_appointment_id'] ?? null,
                'blood_bank_location_id' => $data['blood_bank_location_id'],
                'donation_number' => $this->allocate($data['hospital_id'], 'blood_donation_number'),
                'collection_number' => $this->allocate($data['hospital_id'], 'blood_collection_number'),
                'collected_at' => $data['collected_at'] ?? now(),
                'collected_by' => $actor->id,
                'bag_type' => $data['bag_type'],
                'volume_ml' => $data['volume_ml'] ?? null,
                'status' => 'collected',
                'notes' => $data['notes'] ?? null,
            ]);

            if ($donation->blood_donation_appointment_id) {
                BloodDonationAppointment::whereKey($donation->blood_donation_appointment_id)->update(['status' => 'completed']);
            }
            $this->event($donation, 'blood_bank.collection_recorded', null, $donation->toArray(), $actor);

            return $donation->refresh();
        });
    }

    public function enterGroup(BloodDonation $donation, array $data, User $actor): BloodGroupResult
    {
        abort_if($donation->groupResult()->where('status', 'verified')->exists(), 422, 'Verified blood group results require an amendment.');

        $result = BloodGroupResult::create([
            'hospital_id' => $donation->hospital_id,
            'blood_donation_id' => $donation->id,
            'abo_group' => $data['abo_group'],
            'rh_factor' => $data['rh_factor'],
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
            'entered_by' => $actor->id,
            'entered_at' => now(),
        ]);
        $this->event($result, 'blood_bank.group_result_entered', null, $result->toArray(), $actor);

        return $result;
    }

    public function verifyGroup(BloodGroupResult $result, User $actor): BloodGroupResult
    {
        abort_unless($result->status === 'draft', 422, 'Only draft group results can be verified.');
        abort_if($result->entered_by === $actor->id, 403, 'Blood group verifier must be separate from entry user.');

        return DB::transaction(function () use ($result, $actor): BloodGroupResult {
            $result = BloodGroupResult::whereKey($result->id)->lockForUpdate()->firstOrFail();
            $before = $result->toArray();
            $result->forceFill(['status' => 'verified', 'verified_by' => $actor->id, 'verified_at' => now()])->save();
            BloodComponent::where('blood_donation_id', $result->blood_donation_id)->update(['abo_group' => $result->abo_group, 'rh_factor' => $result->rh_factor]);
            $this->event($result, 'blood_bank.group_result_verified', $before, $result->fresh()->toArray(), $actor);

            return $result->refresh();
        });
    }

    public function recordScreeningResult(BloodDonation $donation, BloodScreeningTest $test, array $data, User $actor): BloodScreeningResult
    {
        abort_unless($donation->hospital_id === $test->hospital_id, 403);
        abort_if($donation->screeningResults()->where('blood_screening_test_id', $test->id)->where('status', 'verified')->exists(), 422, 'Verified screening results require an amendment.');

        $result = BloodScreeningResult::updateOrCreate(
            ['blood_donation_id' => $donation->id, 'blood_screening_test_id' => $test->id],
            [
                'hospital_id' => $donation->hospital_id,
                'lab_specimen_id' => $data['lab_specimen_id'] ?? null,
                'lab_result_id' => $data['lab_result_id'] ?? null,
                'result_value' => $data['result_value'] ?? null,
                'release_cleared' => (bool) ($data['release_cleared'] ?? false),
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'entered_by' => $actor->id,
                'entered_at' => now(),
                'verified_by' => null,
                'verified_at' => null,
            ]
        );
        $this->event($result, 'blood_bank.screening_result_entered', null, $result->toArray(), $actor);

        return $result;
    }

    public function verifyScreeningResult(BloodScreeningResult $result, User $actor): BloodScreeningResult
    {
        abort_unless($result->status === 'draft', 422, 'Only draft screening results can be verified.');
        abort_if($result->entered_by === $actor->id, 403, 'Screening verifier must be separate from entry user.');
        $before = $result->toArray();
        $result->forceFill(['status' => 'verified', 'verified_by' => $actor->id, 'verified_at' => now()])->save();
        $this->event($result, 'blood_bank.screening_result_verified', $before, $result->fresh()->toArray(), $actor);

        return $result->refresh();
    }

    public function prepareComponent(BloodDonation $donation, BloodComponentType $type, BloodBankLocation $location, ?BloodStorageUnit $storage, array $data, User $actor): BloodComponent
    {
        abort_unless($donation->hospital_id === $type->hospital_id && $donation->hospital_id === $location->hospital_id, 403);
        abort_if($storage && $storage->blood_bank_location_id !== $location->id, 403);
        $group = $donation->groupResult()->where('status', 'verified')->latest()->first();

        $component = BloodComponent::create([
            'hospital_id' => $donation->hospital_id,
            'facility_id' => $donation->facility_id,
            'blood_donation_id' => $donation->id,
            'blood_component_type_id' => $type->id,
            'blood_bank_location_id' => $location->id,
            'blood_storage_unit_id' => $storage?->id,
            'component_number' => $this->allocate($donation->hospital_id, 'blood_component_number'),
            'abo_group' => $group?->abo_group,
            'rh_factor' => $group?->rh_factor,
            'volume_ml' => $data['volume_ml'] ?? null,
            'expires_on' => $data['expires_on'] ?? ($type->default_shelf_life_days ? today()->addDays($type->default_shelf_life_days) : null),
            'state' => 'quarantined',
            'prepared_by' => $actor->id,
            'prepared_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);
        $this->event($component, 'blood_bank.component_prepared', null, $component->toArray(), $actor);

        return $component;
    }

    public function releaseComponent(BloodComponent $component, User $actor, string $reason): BloodComponent
    {
        abort_unless($component->state === 'quarantined', 422, 'Only quarantined components can be released.');
        abort_if($component->expires_on && $component->expires_on->isPast() && ! $component->expires_on->isToday(), 422, 'Expired components cannot be released.');
        abort_unless($component->donation->groupResult()->where('status', 'verified')->exists(), 422, 'Verified blood group result is required before release.');
        $requiredTestIds = BloodScreeningTest::where('hospital_id', $component->hospital_id)->where('is_active', true)->where('is_required_for_release', true)->pluck('id');
        $cleared = BloodScreeningResult::where('blood_donation_id', $component->blood_donation_id)->whereIn('blood_screening_test_id', $requiredTestIds)->where('status', 'verified')->where('release_cleared', true)->pluck('blood_screening_test_id')->unique();
        abort_if($requiredTestIds->diff($cleared)->isNotEmpty(), 422, 'Required screening tests must be manually verified and marked cleared before release.');

        return $this->componentState($component, 'available', $actor, 'blood_bank.component_released', $reason, ['released_by' => $actor->id, 'released_at' => now(), 'release_reason' => $reason]);
    }

    public function transferComponent(BloodComponent $component, BloodBankLocation $toLocation, ?BloodStorageUnit $toStorage, User $actor, string $reason): BloodComponentTransfer
    {
        abort_unless($component->isUsableCandidate() || in_array($component->state, ['quarantined', 'processing'], true), 422, 'This component cannot be transferred in its current state.');
        abort_unless($component->hospital_id === $toLocation->hospital_id, 403);
        abort_if($toStorage && $toStorage->blood_bank_location_id !== $toLocation->id, 403);

        return DB::transaction(function () use ($component, $toLocation, $toStorage, $actor, $reason): BloodComponentTransfer {
            $component = BloodComponent::whereKey($component->id)->lockForUpdate()->firstOrFail();
            $before = $component->toArray();
            $transfer = BloodComponentTransfer::create([
                'hospital_id' => $component->hospital_id,
                'blood_component_id' => $component->id,
                'from_location_id' => $component->blood_bank_location_id,
                'to_location_id' => $toLocation->id,
                'from_storage_unit_id' => $component->blood_storage_unit_id,
                'to_storage_unit_id' => $toStorage?->id,
                'reason' => $reason,
                'transferred_by' => $actor->id,
                'transferred_at' => now(),
            ]);
            $component->forceFill(['blood_bank_location_id' => $toLocation->id, 'blood_storage_unit_id' => $toStorage?->id, 'state' => $component->state === 'available' ? 'transferred' : $component->state])->save();
            $this->event($component, 'blood_bank.component_transferred', $before, $component->fresh()->toArray(), $actor, $reason);
            $this->event($transfer, 'blood_bank.transfer_recorded', null, $transfer->toArray(), $actor, $reason);

            return $transfer;
        });
    }

    public function recallComponent(BloodComponent $component, User $actor, string $reason): BloodComponent
    {
        abort_unless(in_array($component->state, ['available', 'reserved', 'transferred'], true), 422, 'Only active inventory can be recalled.');

        return $this->componentState($component, 'recalled', $actor, 'blood_bank.component_recalled', $reason);
    }

    public function discardComponent(BloodComponent $component, User $actor, string $reason): BloodComponent
    {
        abort_unless(! in_array($component->state, ['issued', 'consumed'], true), 422, 'Issued or consumed components cannot be discarded in this phase.');

        return $this->componentState($component, 'discarded', $actor, 'blood_bank.component_discarded', $reason);
    }

    public function amend(Model $subject, string $reason, string $content, User $actor): BloodBankAmendment
    {
        $amendment = BloodBankAmendment::create([
            'hospital_id' => $subject->hospital_id,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'reason' => $reason,
            'content' => $content,
            'authored_by' => $actor->id,
            'authored_at' => now(),
        ]);
        $this->event($subject, 'blood_bank.amended', null, $amendment->toArray(), $actor, $reason);

        return $amendment;
    }

    private function componentState(BloodComponent $component, string $state, User $actor, string $action, string $reason, array $extra = []): BloodComponent
    {
        return DB::transaction(function () use ($component, $state, $actor, $action, $reason, $extra): BloodComponent {
            $component = BloodComponent::whereKey($component->id)->lockForUpdate()->firstOrFail();
            $before = $component->toArray();
            $from = $component->state;
            $component->forceFill(['state' => $state] + $extra)->save();
            $this->event($component, $action, $before, $component->fresh()->toArray(), $actor, $reason, $from, $state);

            return $component->refresh();
        });
    }

    private function allocate(int $hospitalId, string $key): string
    {
        $sequence = NumberSequence::where('hospital_id', $hospitalId)->whereNull('facility_id')->where('key', $key)->where('status', 'active')->firstOrFail();

        return $this->numbers->allocate($sequence);
    }

    private function event(Model $subject, string $action, ?array $before, ?array $after, User $actor, ?string $reason = null, ?string $from = null, ?string $to = null): void
    {
        BloodBankEvent::create([
            'hospital_id' => $subject->hospital_id,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'actor_id' => $actor->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
        $this->audit->record($action, $subject, $before, $after, actor: $actor, reason: $reason);
    }
}
