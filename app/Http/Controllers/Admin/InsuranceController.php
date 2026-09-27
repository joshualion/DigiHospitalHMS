<?php

namespace App\Http\Controllers\Admin;

use App\Models\BillableService;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\PatientCoverage;
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

    private function assertHospital(int $hospitalId): void
    {
        abort_unless($hospitalId === $this->currentHospital()->id,403);
    }
}
