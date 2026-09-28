<?php

namespace App\Http\Controllers\Admin;

use App\Models\BillableService;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Invoice;
use App\Models\PatientCoverage;
use App\Models\PayerClaim;
use App\Models\PayerClaimBatch;
use App\Models\PayerOrganization;
use App\Models\PayerPlan;
use App\Models\PayerPlanTariff;
use App\Models\PayerPreAuthorization;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InsuranceController extends FoundationController
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('insurance.view') || $request->user()->hasRole('superadmin'), 403);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Insurance/Index', [
            'organizations' => PayerOrganization::with(['plans.tariffs.service:id,code,name'])
                ->where('hospital_id', $hospital->id)->orderBy('name')->get(),
            'patients' => Patient::where('hospital_id', $hospital->id)->where('status', 'active')
                ->orderBy('first_name')->limit(200)->get(['id','hospital_number','first_name','middle_name','last_name']),
            'coverages' => PatientCoverage::with(['patient:id,hospital_number,first_name,middle_name,last_name','organization:id,name,type','plan:id,name,code'])
                ->where('hospital_id', $hospital->id)
                ->when($request->patient_id, fn ($q,$id) => $q->where('patient_id',$id))
                ->latest()->paginate(20)->withQueryString(),
            'preAuthorizations' => PayerPreAuthorization::with(['patient:id,hospital_number,first_name,middle_name,last_name','coverage.organization:id,name','coverage.plan:id,name','service:id,code,name'])
                ->where('hospital_id', $hospital->id)->latest()->limit(30)->get(),
            'services' => BillableService::where('hospital_id',$hospital->id)->where('is_active',true)->orderBy('name')->get(['id','code','name']),
            'facilities' => Facility::where('hospital_id',$hospital->id)->where('status','active')->orderBy('name')->get(['id','name']),
            'invoices' => Invoice::with('patient:id,hospital_number,first_name,middle_name,last_name')
                ->where('hospital_id',$hospital->id)->where('status','issued')->where('balance_minor','>',0)
                ->latest()->limit(100)->get(['id','patient_id','invoice_number','currency','total_minor','paid_minor','balance_minor','issued_at']),
            'claims' => PayerClaim::with([
                    'patient:id,hospital_number,first_name,middle_name,last_name',
                    'organization:id,name,credit_days',
                    'plan:id,name,code',
                    'invoice:id,invoice_number,total_minor,balance_minor,currency,issued_at',
                    'batch:id,reference,status',
                ])
                ->where('hospital_id',$hospital->id)
                ->latest()->paginate(25, ['*'], 'claims_page')->withQueryString(),
            'claimBatches' => PayerClaimBatch::with(['organization:id,name','claims:id,payer_claim_batch_id,status,claimed_minor,approved_minor,paid_minor'])
                ->where('hospital_id',$hospital->id)->latest()->limit(50)->get(),
            'receivablesAgeing' => $this->receivablesAgeing($hospital->id),
        ]);
    }

    public function storeOrganization(Request $request, AuditService $audit): RedirectResponse
    {
        abort_unless($request->user()->can('insurance.manage') || $request->user()->hasRole('superadmin'), 403);
        $hospital = $this->currentHospital();
        $validated = $request->validate([
            'type' => ['required', Rule::in(['hmo','insurer','corporate'])],
            'code' => ['required','string','max:40', Rule::unique('payer_organizations')->where('hospital_id',$hospital->id)],
            'name' => ['required','string','max:255'],
            'contact_name' => ['nullable','string','max:255'],
            'email' => ['nullable','email','max:255'],
            'phone' => ['nullable','string','max:50'],
            'address' => ['nullable','string','max:1000'],
            'credit_days' => ['required','integer','min:0','max:3650'],
            'status' => ['required', Rule::in(['active','inactive'])],
            'notes' => ['nullable','string','max:2000'],
        ]);
        $record=PayerOrganization::create($validated+['hospital_id'=>$hospital->id]);
        $audit->record('insurance.organization_created',$record,null,$record->toArray(),actor:$request->user());
        return back()->with('success','Payer organization created.');
    }

    public function updateOrganization(Request $request, PayerOrganization $organization, AuditService $audit): RedirectResponse
    {
        $this->assertHospital($organization->hospital_id);
        abort_unless($request->user()->can('insurance.manage') || $request->user()->hasRole('superadmin'), 403);
        $validated=$request->validate([
            'type'=>['required',Rule::in(['hmo','insurer','corporate'])],
            'code'=>['required','string','max:40',Rule::unique('payer_organizations')->where('hospital_id',$organization->hospital_id)->ignore($organization)],
            'name'=>['required','string','max:255'],'contact_name'=>['nullable','string','max:255'],
            'email'=>['nullable','email','max:255'],'phone'=>['nullable','string','max:50'],
            'address'=>['nullable','string','max:1000'],'credit_days'=>['required','integer','min:0','max:3650'],
            'status'=>['required',Rule::in(['active','inactive'])],'notes'=>['nullable','string','max:2000'],
        ]);
        $before=$organization->toArray(); $organization->update($validated);
        $audit->record('insurance.organization_updated',$organization,$before,$organization->fresh()->toArray(),actor:$request->user());
        return back()->with('success','Payer organization updated.');
    }

    public function storePlan(Request $request, AuditService $audit): RedirectResponse
    {
        abort_unless($request->user()->can('insurance.manage') || $request->user()->hasRole('superadmin'),403);
        $hospital=$this->currentHospital();
        $validated=$request->validate([
            'payer_organization_id'=>['required',Rule::exists('payer_organizations','id')->where('hospital_id',$hospital->id)],
            'code'=>['required','string','max:40'],'name'=>['required','string','max:255'],
            'currency'=>['required','string','size:3'],'requires_pre_authorization'=>['boolean'],
            'status'=>['required',Rule::in(['active','inactive'])],
            'effective_from'=>['nullable','date'],'effective_to'=>['nullable','date','after_or_equal:effective_from'],
            'notes'=>['nullable','string','max:2000'],
        ]);
        $exists=PayerPlan::where('payer_organization_id',$validated['payer_organization_id'])->where('code',$validated['code'])->exists();
        abort_if($exists,422,'Plan code already exists for this payer.');
        $record=PayerPlan::create($validated+['hospital_id'=>$hospital->id]);
        $audit->record('insurance.plan_created',$record,null,$record->toArray(),actor:$request->user());
        return back()->with('success','Payer plan created.');
    }

    public function storeTariff(Request $request, PayerPlan $plan, AuditService $audit): RedirectResponse
    {
        $this->assertHospital($plan->hospital_id);
        abort_unless($request->user()->can('insurance.tariffs.manage') || $request->user()->hasRole('superadmin'),403);
        $validated=$request->validate([
            'billable_service_id'=>['required',Rule::exists('billable_services','id')->where('hospital_id',$plan->hospital_id)],
            'facility_id'=>['nullable',Rule::exists('facilities','id')->where('hospital_id',$plan->hospital_id)],
            'currency'=>['required','string','size:3'],'amount_minor'=>['required','integer','min:0'],
            'effective_from'=>['required','date'],'effective_to'=>['nullable','date','after_or_equal:effective_from'],
            'notes'=>['nullable','string','max:2000'],
        ]);
        $record=PayerPlanTariff::create($validated+['hospital_id'=>$plan->hospital_id,'payer_plan_id'=>$plan->id,'is_active'=>true,'created_by'=>$request->user()->id]);
        $audit->record('insurance.tariff_created',$record,null,$record->toArray(),actor:$request->user());
        return back()->with('success','Plan tariff added.');
    }

    public function storeCoverage(Request $request, AuditService $audit): RedirectResponse
    {
        abort_unless($request->user()->can('insurance.coverage.manage') || $request->user()->hasRole('superadmin'),403);
        $hospital=$this->currentHospital();
        $validated=$request->validate([
            'patient_id'=>['required',Rule::exists('patients','id')->where('hospital_id',$hospital->id)],
            'payer_organization_id'=>['required',Rule::exists('payer_organizations','id')->where('hospital_id',$hospital->id)],
            'payer_plan_id'=>['required',Rule::exists('payer_plans','id')->where('hospital_id',$hospital->id)],
            'member_number'=>['required','string','max:120'],'policy_number'=>['nullable','string','max:120'],
            'principal_member_name'=>['nullable','string','max:255'],'relationship_to_principal'=>['nullable','string','max:120'],
            'employer_name'=>['nullable','string','max:255'],'valid_from'=>['nullable','date'],
            'valid_to'=>['nullable','date','after_or_equal:valid_from'],'is_primary'=>['boolean'],
            'status'=>['required',Rule::in(['active','inactive','expired','suspended'])],'notes'=>['nullable','string','max:2000'],
        ]);
        $plan=PayerPlan::where('hospital_id',$hospital->id)->findOrFail($validated['payer_plan_id']);
        abort_unless($plan->payer_organization_id === (int)$validated['payer_organization_id'],422,'Selected plan does not belong to the selected payer.');

        $record=DB::transaction(function() use($validated,$hospital){
            if($validated['is_primary']??false){
                PatientCoverage::where('hospital_id',$hospital->id)->where('patient_id',$validated['patient_id'])->update(['is_primary'=>false]);
            }
            return PatientCoverage::create($validated+['hospital_id'=>$hospital->id]);
        });
        $audit->record('insurance.coverage_created',$record,null,$record->toArray(),actor:$request->user());
        return back()->with('success','Patient coverage added.');
    }

    public function requestPreAuthorization(Request $request, AuditService $audit): RedirectResponse
    {
        abort_unless($request->user()->can('insurance.preauthorizations.manage') || $request->user()->hasRole('superadmin'),403);
        $hospital=$this->currentHospital();
        $validated=$request->validate([
            'patient_coverage_id'=>['required',Rule::exists('patient_coverages','id')->where('hospital_id',$hospital->id)],
            'billable_service_id'=>['nullable',Rule::exists('billable_services','id')->where('hospital_id',$hospital->id)],
            'clinical_encounter_id'=>['nullable',Rule::exists('clinical_encounters','id')->where('hospital_id',$hospital->id)],
            'reference'=>['nullable','string','max:120'],'requested_amount_minor'=>['nullable','integer','min:0'],
            'clinical_or_service_context'=>['nullable','string','max:3000'],'valid_until'=>['nullable','date'],
        ]);
        $coverage=PatientCoverage::with('plan')->where('hospital_id',$hospital->id)->findOrFail($validated['patient_coverage_id']);
        abort_unless($coverage->isCurrentlyValid(),422,'Patient coverage is not currently active and valid.');
        $record=PayerPreAuthorization::create($validated+[
            'hospital_id'=>$hospital->id,'patient_id'=>$coverage->patient_id,'status'=>'requested',
            'requested_by'=>$request->user()->id,'requested_at'=>now(),
        ]);
        $audit->record('insurance.preauthorization_requested',$record,null,$record->toArray(),actor:$request->user());
        return back()->with('success','Pre-authorisation request recorded.');
    }

    public function decidePreAuthorization(Request $request, PayerPreAuthorization $preAuthorization, AuditService $audit): RedirectResponse
    {
        $this->assertHospital($preAuthorization->hospital_id);
        abort_unless($request->user()->can('insurance.preauthorizations.decide') || $request->user()->hasRole('superadmin'),403);
        abort_unless($preAuthorization->status==='requested',422,'Only requested pre-authorisations can be decided.');
        $validated=$request->validate([
            'status'=>['required',Rule::in(['approved','declined'])],
            'authorization_code'=>['nullable','required_if:status,approved','string','max:120'],
            'approved_amount_minor'=>['nullable','integer','min:0'],'decision_notes'=>['required','string','max:3000'],
            'valid_until'=>['nullable','date'],
        ]);
        $before=$preAuthorization->toArray();
        $preAuthorization->update($validated+['decided_by'=>$request->user()->id,'decided_at'=>now()]);
        $audit->record('insurance.preauthorization_'.$validated['status'],$preAuthorization,$before,$preAuthorization->fresh()->toArray(),actor:$request->user());
        return back()->with('success','Pre-authorisation decision recorded.');
    }

    public function storeClaim(Request $request, AuditService $audit): RedirectResponse
    {
        abort_unless($request->user()->can('insurance.claims.manage') || $request->user()->hasRole('superadmin'),403);
        $hospital=$this->currentHospital();
        $validated=$request->validate([
            'patient_coverage_id'=>['required',Rule::exists('patient_coverages','id')->where('hospital_id',$hospital->id)],
            'invoice_id'=>['required',Rule::exists('invoices','id')->where('hospital_id',$hospital->id)],
            'payer_pre_authorization_id'=>['nullable',Rule::exists('payer_pre_authorizations','id')->where('hospital_id',$hospital->id)],
            'claim_number'=>['required','string','max:120',Rule::unique('payer_claims')->where('hospital_id',$hospital->id)],
            'service_date'=>['nullable','date'],
            'submission_notes'=>['nullable','string','max:3000'],
        ]);

        $coverage=PatientCoverage::with(['organization','plan'])->where('hospital_id',$hospital->id)->findOrFail($validated['patient_coverage_id']);
        abort_unless($coverage->isCurrentlyValid(),422,'Patient coverage is not currently active and valid.');
        $invoice=Invoice::where('hospital_id',$hospital->id)->findOrFail($validated['invoice_id']);
        abort_unless($invoice->patient_id===$coverage->patient_id,422,'Invoice patient does not match the selected coverage.');
        abort_unless(in_array($invoice->status,['issued'],true),422,'Only issued invoices can be claimed.');
        abort_if(PayerClaim::where('hospital_id',$hospital->id)->where('invoice_id',$invoice->id)->whereNotIn('status',['rejected','cancelled'])->exists(),422,'This invoice already has an active claim.');

        $record=PayerClaim::create([
            'hospital_id'=>$hospital->id,
            'patient_id'=>$coverage->patient_id,
            'patient_coverage_id'=>$coverage->id,
            'payer_organization_id'=>$coverage->payer_organization_id,
            'payer_plan_id'=>$coverage->payer_plan_id,
            'invoice_id'=>$invoice->id,
            'payer_pre_authorization_id'=>$validated['payer_pre_authorization_id']??null,
            'claim_number'=>$validated['claim_number'],
            'status'=>'draft',
            'currency'=>$invoice->currency,
            'claimed_minor'=>$invoice->balance_minor,
            'paid_minor'=>0,
            'service_date'=>$validated['service_date']??optional($invoice->issued_at)->toDateString(),
            'due_date'=>now()->addDays($coverage->organization?->credit_days??0)->toDateString(),
            'submission_notes'=>$validated['submission_notes']??null,
            'created_by'=>$request->user()->id,
        ]);

        $audit->record('insurance.claim_created',$record,null,$record->toArray(),actor:$request->user());
        return back()->with('success','Claim created.');
    }

    public function storeClaimBatch(Request $request, AuditService $audit): RedirectResponse
    {
        abort_unless($request->user()->can('insurance.claims.manage') || $request->user()->hasRole('superadmin'),403);
        $hospital=$this->currentHospital();
        $validated=$request->validate([
            'payer_organization_id'=>['required',Rule::exists('payer_organizations','id')->where('hospital_id',$hospital->id)],
            'reference'=>['required','string','max:120',Rule::unique('payer_claim_batches')->where('hospital_id',$hospital->id)],
            'currency'=>['required','string','size:3'],
            'due_date'=>['nullable','date'],
            'notes'=>['nullable','string','max:3000'],
            'claim_ids'=>['required','array','min:1'],
            'claim_ids.*'=>['integer',Rule::exists('payer_claims','id')->where('hospital_id',$hospital->id)],
        ]);

        $batch=DB::transaction(function() use($validated,$hospital,$request){
            $claims=PayerClaim::where('hospital_id',$hospital->id)->whereIn('id',$validated['claim_ids'])->lockForUpdate()->get();
            abort_unless($claims->count()===count($validated['claim_ids']),422,'One or more claims were not found.');
            abort_if($claims->contains(fn($claim)=>$claim->payer_organization_id!==(int)$validated['payer_organization_id']),422,'All claims in a batch must belong to the selected payer.');
            abort_if($claims->contains(fn($claim)=>$claim->status!=='draft' || $claim->payer_claim_batch_id),422,'Only unbatched draft claims can be added to a batch.');
            abort_if($claims->contains(fn($claim)=>$claim->currency!==$validated['currency']),422,'All claims in a batch must use the batch currency.');

            $batch=PayerClaimBatch::create([
                'hospital_id'=>$hospital->id,'payer_organization_id'=>$validated['payer_organization_id'],
                'reference'=>$validated['reference'],'currency'=>$validated['currency'],'status'=>'draft',
                'claimed_minor'=>$claims->sum('claimed_minor'),'approved_minor'=>0,'paid_minor'=>0,
                'due_date'=>$validated['due_date']??null,'created_by'=>$request->user()->id,'notes'=>$validated['notes']??null,
            ]);
            PayerClaim::whereIn('id',$claims->pluck('id'))->update(['payer_claim_batch_id'=>$batch->id]);
            return $batch;
        });

        $audit->record('insurance.claim_batch_created',$batch,null,$batch->load('claims')->toArray(),actor:$request->user());
        return back()->with('success','Claim batch created.');
    }

    public function submitClaim(Request $request, PayerClaim $claim, AuditService $audit): RedirectResponse
    {
        $this->assertHospital($claim->hospital_id);
        abort_unless($request->user()->can('insurance.claims.manage') || $request->user()->hasRole('superadmin'),403);
        abort_unless(in_array($claim->status,['draft','resubmitted'],true),422,'Only draft or resubmitted claims can be submitted.');
        $before=$claim->toArray();
        $claim->update(['status'=>'submitted','submitted_by'=>$request->user()->id,'submitted_at'=>now()]);
        $audit->record('insurance.claim_submitted',$claim,$before,$claim->fresh()->toArray(),actor:$request->user());
        return back()->with('success','Claim submitted.');
    }

    public function decideClaim(Request $request, PayerClaim $claim, AuditService $audit): RedirectResponse
    {
        $this->assertHospital($claim->hospital_id);
        abort_unless($request->user()->can('insurance.claims.decide') || $request->user()->hasRole('superadmin'),403);
        abort_unless($claim->status==='submitted',422,'Only submitted claims can be decided.');
        $validated=$request->validate([
            'status'=>['required',Rule::in(['approved','partially_approved','rejected'])],
            'payer_reference'=>['nullable','string','max:120'],
            'approved_minor'=>['nullable','integer','min:0','max:'.$claim->claimed_minor],
            'decision_notes'=>['required','string','max:3000'],
            'rejection_reason'=>['nullable','required_if:status,rejected','string','max:3000'],
        ]);
        if($validated['status']==='approved') $validated['approved_minor']=$claim->claimed_minor;
        if($validated['status']==='partially_approved') abort_if(empty($validated['approved_minor']),422,'Approved amount is required for a partial approval.');
        if($validated['status']==='rejected') $validated['approved_minor']=0;

        $before=$claim->toArray();
        $claim->update($validated+['decided_by'=>$request->user()->id,'decided_at'=>now()]);
        $this->refreshBatchTotals($claim->payer_claim_batch_id);
        $audit->record('insurance.claim_'.$validated['status'],$claim,$before,$claim->fresh()->toArray(),actor:$request->user());
        return back()->with('success','Claim decision recorded.');
    }

    public function resubmitClaim(Request $request, PayerClaim $claim, AuditService $audit): RedirectResponse
    {
        $this->assertHospital($claim->hospital_id);
        abort_unless($request->user()->can('insurance.claims.manage') || $request->user()->hasRole('superadmin'),403);
        abort_unless($claim->status==='rejected',422,'Only rejected claims can be resubmitted.');
        $validated=$request->validate([
            'claim_number'=>['required','string','max:120',Rule::unique('payer_claims')->where('hospital_id',$claim->hospital_id)],
            'submission_notes'=>['required','string','max:3000'],
        ]);
        $replacement=$claim->replicate(['claim_number','status','payer_reference','approved_minor','paid_minor','decision_notes','rejection_reason','submitted_by','submitted_at','decided_by','decided_at','created_at','updated_at']);
        $replacement->claim_number=$validated['claim_number'];
        $replacement->resubmission_of_claim_id=$claim->id;
        $replacement->payer_claim_batch_id=null;
        $replacement->status='resubmitted';
        $replacement->approved_minor=null;
        $replacement->paid_minor=0;
        $replacement->submission_notes=$validated['submission_notes'];
        $replacement->created_by=$request->user()->id;
        $replacement->save();
        $audit->record('insurance.claim_resubmitted',$replacement,['source_claim_id'=>$claim->id],$replacement->toArray(),actor:$request->user());
        return back()->with('success','Claim resubmission created.');
    }

    public function recordClaimPayment(Request $request, PayerClaim $claim, AuditService $audit): RedirectResponse
    {
        $this->assertHospital($claim->hospital_id);
        abort_unless($request->user()->can('insurance.claims.manage') || $request->user()->hasRole('superadmin'),403);
        abort_unless(in_array($claim->status,['approved','partially_approved','partially_paid'],true),422,'Only approved claims can receive payer payments.');
        $validated=$request->validate(['amount_minor'=>['required','integer','min:1'],'payer_reference'=>['nullable','string','max:120']]);
        $ceiling=$claim->approved_minor??$claim->claimed_minor;
        abort_if($claim->paid_minor+$validated['amount_minor']>$ceiling,422,'Payment cannot exceed the approved claim amount.');
        $before=$claim->toArray();
        $newPaid=$claim->paid_minor+$validated['amount_minor'];
        $claim->update([
            'paid_minor'=>$newPaid,
            'status'=>$newPaid===$ceiling?'paid':'partially_paid',
            'payer_reference'=>$validated['payer_reference']??$claim->payer_reference,
        ]);
        $this->refreshBatchTotals($claim->payer_claim_batch_id);
        $audit->record('insurance.claim_payment_recorded',$claim,$before,$claim->fresh()->toArray(),actor:$request->user());
        return back()->with('success','Claim payment recorded.');
    }

    private function refreshBatchTotals(?int $batchId): void
    {
        if(!$batchId) return;
        $batch=PayerClaimBatch::find($batchId);
        if(!$batch) return;
        $claims=PayerClaim::where('payer_claim_batch_id',$batchId)->get();
        $batch->update([
            'claimed_minor'=>$claims->sum('claimed_minor'),
            'approved_minor'=>$claims->sum(fn($claim)=>(int)($claim->approved_minor??0)),
            'paid_minor'=>$claims->sum('paid_minor'),
            'status'=>$claims->every(fn($claim)=>$claim->status==='paid')?'closed':($batch->status==='draft'?'draft':'submitted'),
        ]);
    }

    private function receivablesAgeing(int $hospitalId): array
    {
        $today=now()->startOfDay();
        $buckets=['current'=>0,'1_30'=>0,'31_60'=>0,'61_90'=>0,'90_plus'=>0];
        $claims=PayerClaim::where('hospital_id',$hospitalId)
            ->whereIn('status',['submitted','approved','partially_approved','partially_paid'])
            ->get();
        foreach($claims as $claim){
            $outstanding=$claim->outstandingMinor();
            if($outstanding<=0) continue;
            if(!$claim->due_date || $claim->due_date->greaterThanOrEqualTo($today)) { $buckets['current']+=$outstanding; continue; }
            $days=$claim->due_date->diffInDays($today);
            if($days<=30) $buckets['1_30']+=$outstanding;
            elseif($days<=60) $buckets['31_60']+=$outstanding;
            elseif($days<=90) $buckets['61_90']+=$outstanding;
            else $buckets['90_plus']+=$outstanding;
        }
        return $buckets;
    }


    private function assertHospital(int $hospitalId): void
    {
        abort_unless($hospitalId === $this->currentHospital()->id,403);
    }
}
