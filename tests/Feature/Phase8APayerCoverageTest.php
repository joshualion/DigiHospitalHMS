<?php

namespace Tests\Feature;

use App\Models\BillableService;
use App\Models\BillableServiceCategory;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\PayerOrganization;
use App\Models\PayerPlan;
use App\Models\PayerPreAuthorization;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase8APayerCoverageTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;
    private Facility $facility;
    private User $officer;
    private Patient $patient;
    private BillableService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->hospital = Hospital::factory()->create();
        $this->facility = Facility::factory()->create(['hospital_id' => $this->hospital->id, 'status' => 'active']);
        $this->officer = User::factory()->create(['access_level' => 'admin']);
        $this->officer->syncRoles(['hmo-claims-officer']);

        \App\Models\StaffProfile::factory()->create([
            'user_id' => $this->officer->id,
            'hospital_id' => $this->hospital->id,
            'staff_category' => 'administrative',
            'is_active' => true,
            'employment_status' => 'active',
        ]);

        $this->patient = Patient::create([
            'hospital_id' => $this->hospital->id,
            'registration_facility_id' => $this->facility->id,
            'registered_by' => $this->officer->id,
            'hospital_number' => 'PAT-HMO-001',
            'first_name' => 'Covered',
            'last_name' => 'Patient',
            'sex' => 'female',
            'status' => 'active',
        ]);

        $category = BillableServiceCategory::create([
            'hospital_id' => $this->hospital->id,
            'name' => 'Consultation',
            'code' => 'CONS',
            'is_active' => true,
        ]);
        $this->service = BillableService::create([
            'hospital_id' => $this->hospital->id,
            'billable_service_category_id' => $category->id,
            'code' => 'GEN-CONS',
            'name' => 'General Consultation',
            'is_tax_exempt' => true,
            'tax_rate_basis_points' => 0,
            'is_discount_eligible' => true,
            'is_active' => true,
        ]);
    }

    public function test_hmo_officer_can_configure_payer_plan_tariff_coverage_and_preauthorization(): void
    {
        $this->actingAs($this->officer)->post('/admin/insurance/organizations', [
            'type' => 'hmo', 'code' => 'HMO1', 'name' => 'Example HMO',
            'credit_days' => 30, 'status' => 'active',
        ])->assertRedirect();

        $organization = PayerOrganization::firstOrFail();

        $this->actingAs($this->officer)->post('/admin/insurance/plans', [
            'payer_organization_id' => $organization->id, 'code' => 'GOLD',
            'name' => 'Gold Plan', 'currency' => 'NGN',
            'requires_pre_authorization' => true, 'status' => 'active',
        ])->assertRedirect();

        $plan = PayerPlan::firstOrFail();

        $this->actingAs($this->officer)->post("/admin/insurance/plans/{$plan->id}/tariffs", [
            'billable_service_id' => $this->service->id,
            'facility_id' => $this->facility->id,
            'currency' => 'NGN', 'amount_minor' => 1500000,
            'effective_from' => today()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($this->officer)->post('/admin/insurance/coverages', [
            'patient_id' => $this->patient->id,
            'payer_organization_id' => $organization->id,
            'payer_plan_id' => $plan->id,
            'member_number' => 'MEM-001',
            'is_primary' => true, 'status' => 'active',
        ])->assertRedirect();

        $coverage = \App\Models\PatientCoverage::firstOrFail();
        $this->assertTrue($coverage->isCurrentlyValid());

        $this->actingAs($this->officer)->post('/admin/insurance/pre-authorizations', [
            'patient_coverage_id' => $coverage->id,
            'billable_service_id' => $this->service->id,
            'requested_amount_minor' => 1500000,
            'clinical_or_service_context' => 'Service authorization request.',
        ])->assertRedirect();

        $authorization = PayerPreAuthorization::firstOrFail();

        $this->actingAs($this->officer)->patch("/admin/insurance/pre-authorizations/{$authorization->id}/decision", [
            'status' => 'approved',
            'authorization_code' => 'AUTH-001',
            'approved_amount_minor' => 1500000,
            'decision_notes' => 'Approved by payer as recorded by authorized staff.',
        ])->assertRedirect();

        $this->assertDatabaseHas('payer_pre_authorizations', [
            'id' => $authorization->id,
            'status' => 'approved',
            'authorization_code' => 'AUTH-001',
        ]);
        $this->assertDatabaseHas('audit_events', ['action' => 'insurance.preauthorization_approved']);
    }

    public function test_plan_must_belong_to_selected_payer_and_records_are_hospital_scoped(): void
    {
        $orgA=PayerOrganization::create(['hospital_id'=>$this->hospital->id,'type'=>'hmo','code'=>'A','name'=>'A','status'=>'active']);
        $orgB=PayerOrganization::create(['hospital_id'=>$this->hospital->id,'type'=>'hmo','code'=>'B','name'=>'B','status'=>'active']);
        $plan=PayerPlan::create(['hospital_id'=>$this->hospital->id,'payer_organization_id'=>$orgA->id,'code'=>'P','name'=>'Plan','currency'=>'NGN','status'=>'active']);

        $this->actingAs($this->officer)->post('/admin/insurance/coverages', [
            'patient_id'=>$this->patient->id,
            'payer_organization_id'=>$orgB->id,
            'payer_plan_id'=>$plan->id,
            'member_number'=>'BAD',
            'is_primary'=>false,
            'status'=>'active',
        ])->assertStatus(422);

        $other=Hospital::factory()->create();
        $otherOrg=PayerOrganization::create(['hospital_id'=>$other->id,'type'=>'hmo','code'=>'OTHER','name'=>'Other','status'=>'active']);
        $this->actingAs($this->officer)->patch("/admin/insurance/organizations/{$otherOrg->id}", [
            'type'=>'hmo','code'=>'OTHER','name'=>'Tampered','credit_days'=>0,'status'=>'active',
        ])->assertForbidden();
    }

    public function test_unrelated_clinical_role_does_not_receive_insurance_management_permissions(): void
    {
        $doctor=User::factory()->create(['access_level'=>'admin']);
        $doctor->syncRoles(['doctor']);
        $this->assertFalse($doctor->can('insurance.manage'));
        $this->assertFalse($doctor->can('insurance.tariffs.manage'));
    }
}
