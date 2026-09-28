<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admission;
use App\Models\Invoice;
use App\Models\LabRequest;
use App\Models\Patient;
use App\Models\PayerClaim;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\RadiologyRequest;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportingController extends FoundationController
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('reports.view') || $request->user()->hasRole('superadmin'), 403);
        $hospital=$this->currentHospital();
        [$from,$to]=$this->range($request);

        return Inertia::render('Admin/Reports/Index', [
            'filters'=>['from'=>$from->toDateString(),'to'=>$to->toDateString()],
            'summary'=>$this->summary($hospital->id,$from,$to),
            'daily'=>$this->daily($hospital->id,$from,$to),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('reports.export') || $request->user()->hasRole('superadmin'), 403);
        $hospital=$this->currentHospital();
        [$from,$to]=$this->range($request);
        $type=$request->validate(['type'=>['required','in:financial,clinical,operations']])['type'];

        return response()->streamDownload(function() use($hospital,$from,$to,$type): void {
            $out=fopen('php://output','w');
            if($type==='financial'){
                fputcsv($out,['Date','Invoices Issued','Invoice Value','Payments Posted','Payment Value','Open Claim Receivable']);
                foreach($this->daily($hospital->id,$from,$to) as $row){
                    fputcsv($out,[$row['date'],$row['invoices_count'],$row['invoice_minor'],$row['payments_count'],$row['payment_minor'],$row['claim_receivable_minor']]);
                }
            } elseif($type==='clinical'){
                fputcsv($out,['Date','Visits','Admissions','Lab Requests','Radiology Requests','Prescriptions']);
                foreach($this->daily($hospital->id,$from,$to) as $row){
                    fputcsv($out,[$row['date'],$row['visits'],$row['admissions'],$row['lab_requests'],$row['radiology_requests'],$row['prescriptions']]);
                }
            } else {
                fputcsv($out,['Metric','Value']);
                foreach($this->summary($hospital->id,$from,$to) as $key=>$value){
                    fputcsv($out,[$key,is_array($value)?json_encode($value):$value]);
                }
            }
            fclose($out);
        }, "hospital-report-{$type}-{$from->toDateString()}-{$to->toDateString()}.csv", ['Content-Type'=>'text/csv']);
    }

    private function range(Request $request): array
    {
        $validated=$request->validate([
            'from'=>['nullable','date'],
            'to'=>['nullable','date','after_or_equal:from'],
        ]);
        $to=CarbonImmutable::parse($validated['to']??today()->toDateString())->endOfDay();
        $from=CarbonImmutable::parse($validated['from']??$to->subDays(29)->toDateString())->startOfDay();
        abort_if($from->diffInDays($to)>366,422,'Reporting range cannot exceed 366 days.');
        return [$from,$to];
    }

    private function summary(int $hospitalId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $between=[$from,$to];
        $invoiceValue=(int)Invoice::where('hospital_id',$hospitalId)->whereBetween('created_at',$between)->whereIn('status',['issued','voided'])->sum('total_minor');
        $paymentValue=(int)Payment::where('hospital_id',$hospitalId)->whereBetween('posted_at',$between)->where('status','posted')->sum('amount_minor');
        $claimReceivable=(int)PayerClaim::where('hospital_id',$hospitalId)
            ->whereIn('status',['submitted','approved','partially_approved','partially_paid'])
            ->get()->sum(fn(PayerClaim $claim)=>$claim->outstandingMinor());

        return [
            'registered_patients'=>Patient::where('hospital_id',$hospitalId)->whereBetween('created_at',$between)->count(),
            'visits'=>Visit::where('hospital_id',$hospitalId)->whereBetween('created_at',$between)->count(),
            'admissions'=>Admission::where('hospital_id',$hospitalId)->whereBetween('created_at',$between)->count(),
            'lab_requests'=>LabRequest::where('hospital_id',$hospitalId)->whereBetween('created_at',$between)->count(),
            'radiology_requests'=>RadiologyRequest::where('hospital_id',$hospitalId)->whereBetween('created_at',$between)->count(),
            'prescriptions'=>Prescription::where('hospital_id',$hospitalId)->whereBetween('created_at',$between)->count(),
            'invoices_issued'=>Invoice::where('hospital_id',$hospitalId)->whereBetween('issued_at',$between)->count(),
            'invoice_value_minor'=>$invoiceValue,
            'payments_posted'=>Payment::where('hospital_id',$hospitalId)->whereBetween('posted_at',$between)->where('status','posted')->count(),
            'payment_value_minor'=>$paymentValue,
            'claim_receivable_minor'=>$claimReceivable,
            'outstanding_invoice_minor'=>(int)Invoice::where('hospital_id',$hospitalId)->where('balance_minor','>',0)->sum('balance_minor'),
            'active_admissions'=>Admission::where('hospital_id',$hospitalId)->whereIn('status',['admitted','transferred'])->count(),
        ];
    }

    private function daily(int $hospitalId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days=[];
        for($day=$from->startOfDay();$day->lte($to);$day=$day->addDay()){
            $start=$day->startOfDay(); $end=$day->endOfDay();
            $claims=PayerClaim::where('hospital_id',$hospitalId)->whereDate('created_at','<=',$end->toDateString())
                ->whereIn('status',['submitted','approved','partially_approved','partially_paid'])->get();
            $days[]=[
                'date'=>$day->toDateString(),
                'visits'=>Visit::where('hospital_id',$hospitalId)->whereBetween('created_at',[$start,$end])->count(),
                'admissions'=>Admission::where('hospital_id',$hospitalId)->whereBetween('created_at',[$start,$end])->count(),
                'lab_requests'=>LabRequest::where('hospital_id',$hospitalId)->whereBetween('created_at',[$start,$end])->count(),
                'radiology_requests'=>RadiologyRequest::where('hospital_id',$hospitalId)->whereBetween('created_at',[$start,$end])->count(),
                'prescriptions'=>Prescription::where('hospital_id',$hospitalId)->whereBetween('created_at',[$start,$end])->count(),
                'invoices_count'=>Invoice::where('hospital_id',$hospitalId)->whereBetween('issued_at',[$start,$end])->count(),
                'invoice_minor'=>(int)Invoice::where('hospital_id',$hospitalId)->whereBetween('issued_at',[$start,$end])->sum('total_minor'),
                'payments_count'=>Payment::where('hospital_id',$hospitalId)->whereBetween('posted_at',[$start,$end])->where('status','posted')->count(),
                'payment_minor'=>(int)Payment::where('hospital_id',$hospitalId)->whereBetween('posted_at',[$start,$end])->where('status','posted')->sum('amount_minor'),
                'claim_receivable_minor'=>(int)$claims->sum(fn(PayerClaim $claim)=>$claim->outstandingMinor()),
            ];
        }
        return $days;
    }
}
