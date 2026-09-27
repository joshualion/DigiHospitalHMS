<?php

namespace App\Http\Controllers\Admin;

use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimBatch;
use App\Models\Invoice;
use App\Models\PatientCoverage;
use App\Models\PayerOrganization;
use App\Services\InsuranceClaimWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InsuranceClaimController extends FoundationController
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('insurance.claims.view') || $request->user()->hasRole('superadmin'),403);
        $hospital=$this->currentHospital();
        $claimedInvoiceIds=InsuranceClaim::where('hospital_id',$hospital->id)->pluck('invoice_id');

        $claims=InsuranceClaim::with([
                'patient:id,hospital_number,first_name,middle_name,last_name',
                'organization:id,name,type,credit_days',
                'plan:id,name,code',
                'coverage:id,member_number',
                'invoice:id,invoice_number,total_minor,status',
                'lines','payments',
            ])->where('hospital_id',$hospital->id)
            ->when($request->status,fn($q,$status)=>$q->where('status',$status))
            ->latest()->paginate(20)->withQueryString();

        $open=InsuranceClaim::where('hospital_id',$hospital->id)
            ->whereIn('status',['approved','partially_approved','partially_paid'])
            ->where('outstanding_minor','>',0)->get(['id','outstanding_minor','decided_at']);

        $aging=['current_0_30'=>0,'days_31_60'=>0,'days_61_90'=>0,'over_90'=>0,'total'=>0];
        foreach($open as $claim){
            $days=$claim->decided_at ? (int)$claim->decided_at->diffInDays(now()) : 0;
            $amount=(int)$claim->outstanding_minor; $aging['total']+=$amount;
            if($days<=30) $aging['current_0_30']+=$amount;
            elseif($days<=60) $aging['days_31_60']+=$amount;
            elseif($days<=90) $aging['days_61_90']+=$amount;
            else $aging['over_90']+=$amount;
        }

        return Inertia::render('Admin/Insurance/Claims',[
            'claims'=>$claims,
            'aging'=>$aging,
            'organizations'=>PayerOrganization::where('hospital_id',$hospital->id)->where('status','active')->orderBy('name')->get(['id','name','type']),
            'coverages'=>PatientCoverage::with(['patient:id,hospital_number,first_name,middle_name,last_name','organization:id,name','plan:id,name,code'])
                ->where('hospital_id',$hospital->id)->where('status','active')->latest()->limit(300)->get(),
            'invoices'=>Invoice::with('patient:id,hospital_number,first_name,middle_name,last_name')
                ->where('hospital_id',$hospital->id)->where('status','issued')
                ->whereNotIn('id',$claimedInvoiceIds)->latest()->limit(200)
                ->get(['id','patient_id','facility_id','invoice_number','currency','total_minor','issued_at','status']),
            'batches'=>InsuranceClaimBatch::with('organization:id,name')->where('hospital_id',$hospital->id)->latest()->limit(30)->get(),
        ]);
    }

    public function storeClaim(Request $request, InsuranceClaimWorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('insurance.claims.manage') || $request->user()->hasRole('superadmin'),403);
        $hospital=$this->currentHospital();
        $validated=$request->validate([
            'invoice_id'=>['required',Rule::exists('invoices','id')->where('hospital_id',$hospital->id)],
            'patient_coverage_id'=>['required',Rule::exists('patient_coverages','id')->where('hospital_id',$hospital->id)],
        ]);
        abort_if(InsuranceClaim::where('hospital_id',$hospital->id)->where('invoice_id',$validated['invoice_id'])->exists(),422,'This invoice already has a claim.');
        $invoice=Invoice::where('hospital_id',$hospital->id)->findOrFail($validated['invoice_id']);
        $coverage=PatientCoverage::where('hospital_id',$hospital->id)->findOrFail($validated['patient_coverage_id']);
        $workflow->createFromInvoice($invoice,$coverage,$request->user());
        return back()->with('success','Draft claim created from payer tariffs.');
    }

    public function submitClaim(Request $request, InsuranceClaim $claim, InsuranceClaimWorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('insurance.claims.manage') || $request->user()->hasRole('superadmin'),403);
        $validated=$request->validate(['external_reference'=>['nullable','string','max:150'],'submission_notes'=>['nullable','string','max:3000']]);
        $workflow->submit($claim,$validated,$request->user());
        return back()->with('success',$claim->status==='rejected'?'Claim resubmitted.':'Claim submitted.');
    }

    public function decideClaim(Request $request, InsuranceClaim $claim, InsuranceClaimWorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('insurance.claims.decide') || $request->user()->hasRole('superadmin'),403);
        $validated=$request->validate([
            'status'=>['required',Rule::in(['approved','partially_approved','rejected'])],
            'decision_notes'=>['required','string','max:4000'],
            'line_decisions'=>['array'],
            'line_decisions.*.line_id'=>['required','integer'],
            'line_decisions.*.approved_minor'=>['required','integer','min:0'],
            'line_decisions.*.reason'=>['nullable','string','max:2000'],
        ]);
        $workflow->decide($claim,$validated,$request->user());
        return back()->with('success','Claim decision recorded.');
    }

    public function recordPayment(Request $request, InsuranceClaim $claim, InsuranceClaimWorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('insurance.claims.payments') || $request->user()->hasRole('superadmin'),403);
        $validated=$request->validate([
            'amount_minor'=>['required','integer','min:1'],'reference'=>['nullable','string','max:150'],
            'received_on'=>['required','date'],'notes'=>['nullable','string','max:3000'],
        ]);
        $workflow->recordPayment($claim,$validated,$request->user());
        return back()->with('success','Payer remittance recorded against claim.');
    }

    public function storeBatch(Request $request, InsuranceClaimWorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('insurance.claims.batches') || $request->user()->hasRole('superadmin'),403);
        $hospital=$this->currentHospital();
        $validated=$request->validate([
            'payer_organization_id'=>['required',Rule::exists('payer_organizations','id')->where('hospital_id',$hospital->id)],
            'currency'=>['required','string','size:3'],'claim_ids'=>['required','array','min:1'],
            'claim_ids.*'=>['required','integer'],'notes'=>['nullable','string','max:3000'],
        ]);
        $workflow->createBatch($hospital->id,(int)$validated['payer_organization_id'],$validated['currency'],$validated['claim_ids'],$validated,$request->user());
        return back()->with('success','Claim batch created.');
    }

    public function submitBatch(Request $request, InsuranceClaimBatch $batch, InsuranceClaimWorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('insurance.claims.batches') || $request->user()->hasRole('superadmin'),403);
        abort_unless($batch->hospital_id===$this->currentHospital()->id,403);
        $validated=$request->validate(['external_reference'=>['nullable','string','max:150']]);
        $workflow->submitBatch($batch,$validated,$request->user());
        return back()->with('success','Claim batch submitted.');
    }
}
