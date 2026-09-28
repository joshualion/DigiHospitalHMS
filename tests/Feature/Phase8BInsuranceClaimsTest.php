<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Hospital;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientCoverage;
use App\Models\PayerClaim;
use App\Models\PayerClaimBatch;
use App\Models\PayerOrganization;
use App\Models\PayerPlan;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase8BInsuranceClaimsTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;
    private User $officer;
    private Patient $patient;
    private PayerOrganization $payer;
    private PayerPlan $plan;
    private PatientCoverage $coverage;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->hospital = Hospital::factory()->create(['default_currency' => 'NGN']);
        $facility = Facility::factory()->create(['hospital_id' => $this->hospital->id, 'status' => 'active']);

        $this->officer = User::factory()->create(['access_level' => 'admin']);
        $this->officer->syncRoles(['hmo-claims-officer']);
        StaffProfile::factory()->create([
            'user_id' => $this->officer->id,
            'hospital_id' => $this->hospital->id,
            'staff_category' => 'administrative',
            'is_active' => true,
            'employment_status' => 'active',
        ]);

        $this->patient = Patient::create([
            'hospital_id' => $this->hospital->id,
            'registration_facility_id' => $facility->id,
            'registered_by' => $this->officer->id,
            'hospital_number' => 'PAT-CLAIM-001',
            'first_name' => 'Claim',
            'last_name' => 'Patient',
            'sex' => 'female',
            'status' => 'active',
        ]);

        $this->payer = PayerOrganization::create([
            'hospital_id' => $this->hospital->id,
            'type' => 'hmo',
            'code' => 'PAYER1',
            'name' => 'Example Payer',
            'credit_days' => 30,
            'status' => 'active',
        ]);

        $this->plan = PayerPlan::create([
            'hospital_id' => $this->hospital->id,
            'payer_organization_id' => $this->payer->id,
            'code' => 'PLAN1',
            'name' => 'Plan One',
            'currency' => 'NGN',
            'status' => 'active',
        ]);

        $this->coverage = PatientCoverage::create([
            'hospital_id' => $this->hospital->id,
            'patient_id' => $this->patient->id,
            'payer_organization_id' => $this->payer->id,
            'payer_plan_id' => $this->plan->id,
            'member_number' => 'MEM-CLAIM-001',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $this->invoice = Invoice::create([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $facility->id,
            'patient_id' => $this->patient->id,
            'invoice_number' => 'INV-CLAIM-001',
            'status' => 'issued',
            'currency' => 'NGN',
            'subtotal_minor' => 100000,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 100000,
            'paid_minor' => 0,
            'balance_minor' => 100000,
            'payment_status' => 'unpaid',
            'created_by' => $this->officer->id,
            'issued_by' => $this->officer->id,
            'issued_at' => now(),
        ]);
    }

    public function test_claim_can_be_created_batched_submitted_approved_and_paid(): void
    {
        $this->actingAs($this->officer)->post('/admin/insurance/claims', [
            'patient_coverage_id' => $this->coverage->id,
            'invoice_id' => $this->invoice->id,
            'claim_number' => 'CLM-001',
            'service_date' => today()->toDateString(),
            'submission_notes' => 'Initial claim.',
        ])->assertRedirect();

        $claim = PayerClaim::firstOrFail();
        $this->assertSame('draft', $claim->status);
        $this->assertSame(100000, $claim->claimed_minor);

        $this->actingAs($this->officer)->post('/admin/insurance/claim-batches', [
            'payer_organization_id' => $this->payer->id,
            'reference' => 'BATCH-001',
            'currency' => 'NGN',
            'claim_ids' => [$claim->id],
        ])->assertRedirect();

        $batch = PayerClaimBatch::firstOrFail();

        $this->actingAs($this->officer)
            ->patch("/admin/insurance/claim-batches/{$batch->id}/submit")
            ->assertRedirect();

        $this->assertSame('submitted', $claim->refresh()->status);
        $this->assertSame('submitted', $batch->refresh()->status);

        $this->actingAs($this->officer)->patch("/admin/insurance/claims/{$claim->id}/decision", [
            'status' => 'partially_approved',
            'approved_minor' => 80000,
            'payer_reference' => 'PAYER-DEC-1',
            'decision_notes' => 'Partial approval recorded.',
        ])->assertRedirect();

        $this->actingAs($this->officer)->post("/admin/insurance/claims/{$claim->id}/payments", [
            'amount_minor' => 50000,
            'payer_reference' => 'PAY-1',
        ])->assertRedirect();

        $claim->refresh();
        $this->assertSame('partially_paid', $claim->status);
        $this->assertSame(50000, $claim->paid_minor);
        $this->assertSame(30000, $claim->outstandingMinor());
        $this->assertDatabaseHas('audit_events', ['action' => 'insurance.claim_payment_recorded']);
    }

    public function test_rejected_claim_can_be_resubmitted_with_new_claim_number(): void
    {
        $claim = PayerClaim::create([
            'hospital_id' => $this->hospital->id,
            'patient_id' => $this->patient->id,
            'patient_coverage_id' => $this->coverage->id,
            'payer_organization_id' => $this->payer->id,
            'payer_plan_id' => $this->plan->id,
            'invoice_id' => $this->invoice->id,
            'claim_number' => 'CLM-REJECTED',
            'status' => 'rejected',
            'currency' => 'NGN',
            'claimed_minor' => 100000,
            'approved_minor' => 0,
            'paid_minor' => 0,
            'rejection_reason' => 'Missing supporting record.',
            'created_by' => $this->officer->id,
        ]);

        $this->actingAs($this->officer)->post("/admin/insurance/claims/{$claim->id}/resubmit", [
            'claim_number' => 'CLM-RESUBMITTED',
            'submission_notes' => 'Supporting record added.',
        ])->assertRedirect();

        $replacement = PayerClaim::where('claim_number', 'CLM-RESUBMITTED')->firstOrFail();
        $this->assertSame('resubmitted', $replacement->status);
        $this->assertSame($claim->id, $replacement->resubmission_of_claim_id);
    }
}
