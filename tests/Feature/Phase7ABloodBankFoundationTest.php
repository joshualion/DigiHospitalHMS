<?php

namespace Tests\Feature;

use App\Models\BloodBankEvent;
use App\Models\BloodBankLocation;
use App\Models\BloodComponentType;
use App\Models\BloodDonor;
use App\Models\BloodDonorCategory;
use App\Models\BloodScreeningTest;
use App\Models\BloodStorageUnit;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\NumberSequence;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\BloodBankWorkflowService;
use App\Services\SensitiveLookup;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase7ABloodBankFoundationTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;

    private Facility $facility;

    private User $collector;

    private User $verifier;

    private BloodBankLocation $location;

    private BloodStorageUnit $storage;

    private BloodDonorCategory $category;

    private BloodComponentType $componentType;

    private BloodScreeningTest $screeningTest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->hospital = Hospital::factory()->create(['default_currency' => 'NGN']);
        $this->facility = Facility::factory()->create(['hospital_id' => $this->hospital->id, 'status' => 'active']);
        HospitalSetting::create(['hospital_id' => $this->hospital->id, 'default_facility_id' => $this->facility->id, 'currency' => 'NGN']);

        foreach ([['blood_donor_number', 'BDN'], ['blood_donation_number', 'DON'], ['blood_collection_number', 'BAG'], ['blood_component_number', 'BCP']] as [$key, $prefix]) {
            NumberSequence::create(['hospital_id' => $this->hospital->id, 'key' => $key, 'label' => $key, 'prefix' => $prefix, 'date_format' => 'Y', 'padding_length' => 4, 'next_value' => 1, 'status' => 'active']);
        }

        $permissions = ['blood-bank.view', 'blood-bank.catalogue.manage', 'blood-bank.donors.manage', 'blood-bank.screening.manage', 'blood-bank.collections.manage', 'blood-bank.testing.manage', 'blood-bank.testing.verify', 'blood-bank.components.manage', 'blood-bank.components.release', 'blood-bank.amend'];
        $this->collector = $this->staffUser($permissions);
        $this->verifier = $this->staffUser($permissions);

        $this->location = BloodBankLocation::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'code' => 'BB', 'name' => 'Blood Bank', 'is_active' => true]);
        $this->storage = BloodStorageUnit::create(['hospital_id' => $this->hospital->id, 'blood_bank_location_id' => $this->location->id, 'code' => 'FR1', 'name' => 'Fridge 1', 'status' => 'active']);
        $this->category = BloodDonorCategory::create(['hospital_id' => $this->hospital->id, 'code' => 'VOL', 'name' => 'Voluntary', 'is_active' => true]);
        $this->componentType = BloodComponentType::create(['hospital_id' => $this->hospital->id, 'code' => 'WB', 'name' => 'Whole blood', 'default_shelf_life_days' => 35, 'is_active' => true]);
        $this->screeningTest = BloodScreeningTest::create(['hospital_id' => $this->hospital->id, 'code' => 'CFG', 'name' => 'Configured screening', 'is_required_for_release' => true, 'is_active' => true]);
    }

    public function test_donor_privacy_eligibility_decision_collection_numbering_and_quarantine(): void
    {
        $workflow = app(BloodBankWorkflowService::class);
        $donor = $workflow->registerDonor($this->donorPayload(), $this->collector);
        $workflow->recordScreeningDecision($donor, ['eligibility_status' => 'eligible', 'decision_reason' => 'Manual screening approved by authorized staff.', 'responses' => ['configured_question' => 'yes']], $this->collector);
        $donation = $workflow->collect($this->collectionPayload($donor), $this->collector);
        $component = $workflow->prepareComponent($donation, $this->componentType, $this->location, $this->storage, ['volume_ml' => 450], $this->collector);

        $this->assertSame('BDN-'.now()->format('Y').'-0001', $donor->donor_number);
        $this->assertSame('DON-'.now()->format('Y').'-0001', $donation->donation_number);
        $this->assertSame('BAG-'.now()->format('Y').'-0001', $donation->collection_number);
        $this->assertSame('BCP-'.now()->format('Y').'-0001', $component->component_number);
        $this->assertSame('quarantined', $component->state);
        $this->assertNotSame('08030000000', $donor->getRawOriginal('phone_encrypted'));
        $this->assertSame(app(SensitiveLookup::class)->hash('08030000000'), $donor->phone_hash);
        $this->assertDatabaseHas('blood_bank_events', ['action' => 'blood_bank.collection_recorded']);
    }

    public function test_manual_testing_verification_release_transfer_recall_discard_and_amendments(): void
    {
        $workflow = app(BloodBankWorkflowService::class);
        $donation = $this->eligibleDonation();
        $component = $workflow->prepareComponent($donation, $this->componentType, $this->location, $this->storage, ['volume_ml' => 250], $this->collector);

        $this->expectException(HttpException::class);
        $workflow->releaseComponent($component->fresh(), $this->verifier, 'Attempt before testing');
    }

    public function test_release_after_verified_clearance_and_custody_controls(): void
    {
        $workflow = app(BloodBankWorkflowService::class);
        $donation = $this->eligibleDonation();
        $group = $workflow->enterGroup($donation, ['abo_group' => 'O', 'rh_factor' => 'positive'], $this->collector);
        $this->expectException(HttpException::class);
        $workflow->verifyGroup($group->fresh(), $this->collector);
    }

    public function test_release_transfer_recall_discard_authorization_pages_and_cross_hospital_isolation(): void
    {
        $workflow = app(BloodBankWorkflowService::class);
        $donation = $this->eligibleDonation();
        $group = $workflow->enterGroup($donation, ['abo_group' => 'O', 'rh_factor' => 'positive'], $this->collector);
        $workflow->verifyGroup($group->fresh(), $this->verifier);
        $screening = $workflow->recordScreeningResult($donation, $this->screeningTest, ['result_value' => 'Manually cleared', 'release_cleared' => true], $this->collector);
        $workflow->verifyScreeningResult($screening->fresh(), $this->verifier);
        $component = $workflow->prepareComponent($donation, $this->componentType, $this->location, $this->storage, ['volume_ml' => 250, 'expires_on' => today()->addDays(10)], $this->collector);
        $workflow->releaseComponent($component->fresh(), $this->verifier, 'Required tests verified and manually cleared.');

        $target = BloodBankLocation::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'code' => 'SAT', 'name' => 'Satellite Store', 'is_active' => true]);
        $workflow->transferComponent($component->fresh(), $target, null, $this->collector, 'Move to satellite storage');
        $workflow->recallComponent($component->fresh(), $this->verifier, 'Manual recall');
        $workflow->amend($component->fresh(), 'Label note correction', 'Append-only correction.', $this->verifier);
        $workflow->discardComponent($component->fresh(), $this->verifier, 'Discard recalled component');

        $this->assertSame('discarded', $component->refresh()->state);
        $this->assertDatabaseHas('blood_component_transfers', ['reason' => 'Move to satellite storage']);
        $this->assertDatabaseHas('blood_bank_amendments', ['reason' => 'Label note correction']);
        $this->assertTrue(BloodBankEvent::where('action', 'blood_bank.component_recalled')->exists());
        $viewer = User::factory()->create(['access_level' => 'cashier']);
        $viewer->syncRoles(['cashier']);
        StaffProfile::factory()->create(['user_id' => $viewer->id, 'hospital_id' => $this->hospital->id, 'is_active' => true]);
        $this->actingAs($viewer)->post('/admin/blood-bank/collections', [])->assertForbidden();
        $this->actingAs($this->collector)->get('/admin/blood-bank')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/BloodBank/Index')->has('donors')->has('reports'));
        $this->actingAs($this->collector)->get("/admin/blood-bank/donors/{$donation->blood_donor_id}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/BloodBank/DonorShow')->has('donor.donations'));
        $this->actingAs($this->collector)->get("/admin/blood-bank/donations/{$donation->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/BloodBank/DonationShow')->has('donation.components'));

        $otherHospital = Hospital::factory()->create();
        $otherDonor = BloodDonor::create([
            'hospital_id' => $otherHospital->id,
            'registered_by' => $this->collector->id,
            'donor_number' => 'OTHER',
            'first_name' => 'Other',
            'last_name' => 'Donor',
            'status' => 'active',
        ]);
        $this->actingAs($this->collector)->get("/admin/blood-bank/donors/{$otherDonor->id}")->assertForbidden();
    }

    public function test_expired_component_cannot_be_released(): void
    {
        $workflow = app(BloodBankWorkflowService::class);
        $donation = $this->eligibleDonation();
        $workflow->verifyGroup($workflow->enterGroup($donation, ['abo_group' => 'A', 'rh_factor' => 'negative'], $this->collector)->fresh(), $this->verifier);
        $result = $workflow->recordScreeningResult($donation, $this->screeningTest, ['result_value' => 'Cleared', 'release_cleared' => true], $this->collector);
        $workflow->verifyScreeningResult($result->fresh(), $this->verifier);
        $component = $workflow->prepareComponent($donation, $this->componentType, $this->location, $this->storage, ['expires_on' => today()->subDay()], $this->collector);

        $this->expectException(HttpException::class);
        $workflow->releaseComponent($component, $this->verifier, 'Expired release attempt');
    }

    private function eligibleDonation()
    {
        $workflow = app(BloodBankWorkflowService::class);
        $donor = $workflow->registerDonor($this->donorPayload(['phone' => '08031111111']), $this->collector);
        $workflow->recordScreeningDecision($donor, ['eligibility_status' => 'eligible', 'decision_reason' => 'Manual eligible decision'], $this->collector);

        return $workflow->collect($this->collectionPayload($donor), $this->collector);
    }

    private function donorPayload(array $overrides = []): array
    {
        return array_merge([
            'hospital_id' => $this->hospital->id,
            'blood_donor_category_id' => $this->category->id,
            'first_name' => 'Blood',
            'last_name' => 'Donor',
            'phone' => '08030000000',
            'email' => 'donor@example.test',
            'identifier_type' => 'configured_id',
            'identifier_value' => 'DONOR-ID-1',
            'address' => 'Encrypted address',
            'consented_at' => now(),
            'consent_reference' => 'CONSENT-1',
        ], $overrides);
    }

    private function collectionPayload(BloodDonor $donor): array
    {
        return [
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'blood_donor_id' => $donor->id,
            'blood_bank_location_id' => $this->location->id,
            'bag_type' => 'Configured bag',
            'volume_ml' => 450,
        ];
    }

    private function staffUser(array $permissions): User
    {
        $user = User::factory()->create(['access_level' => 'admin']);
        $user->syncRoles(['blood-bank-staff']);
        $user->givePermissionTo($permissions);
        StaffProfile::factory()->create(['user_id' => $user->id, 'hospital_id' => $this->hospital->id, 'is_active' => true]);

        return $user->load('staffProfile');
    }
}
