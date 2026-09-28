<?php

namespace App\Http\Controllers\Admin;

use App\Models\BackupMonitorEvent;
use App\Models\InstallationLicense;
use App\Models\IntegrationConfiguration;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CommercialAdminController extends FoundationController
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('commercial-admin.view') || $request->user()->hasRole('superadmin'),403);
        $hospital=$this->currentHospital();
        $license=InstallationLicense::firstOrCreate(
            ['hospital_id'=>$hospital->id],
            ['plan'=>'standard','status'=>'trial','starts_on'=>today(),'licensed_facilities'=>1]
        );

        $integrations=collect(['payment','accounting','sms'])->map(function(string $type) use($hospital) {
            $record=IntegrationConfiguration::firstOrCreate(
                ['hospital_id'=>$hospital->id,'type'=>$type],
                ['provider'=>'not-configured','status'=>'disabled','configuration'=>[]]
            );
            return [
                'id'=>$record->id,'type'=>$record->type,'provider'=>$record->provider,'status'=>$record->status,
                'last_checked_at'=>$record->last_checked_at?->toISOString(),
                'last_check_status'=>$record->last_check_status,'last_check_message'=>$record->last_check_message,
                'configured'=>collect($record->configuration ?? [])->filter(fn($value)=>filled($value))->isNotEmpty(),
            ];
        });

        return Inertia::render('Admin/Commercial/Index',[
            'license'=>$license,
            'integrations'=>$integrations,
            'backupEvents'=>BackupMonitorEvent::where('hospital_id',$hospital->id)->latest('backup_completed_at')->limit(30)->get(),
            'latestBackup'=>BackupMonitorEvent::where('hospital_id',$hospital->id)->where('status','success')->latest('backup_completed_at')->first(),
            'latestRestoreVerification'=>BackupMonitorEvent::where('hospital_id',$hospital->id)->whereNotNull('restore_verified_at')->latest('restore_verified_at')->first(),
        ]);
    }

    public function updateLicense(Request $request, AuditService $audit): RedirectResponse
    {
        abort_unless($request->user()->can('commercial-admin.manage') || $request->user()->hasRole('superadmin'),403);
        $hospital=$this->currentHospital();
        $validated=$request->validate([
            'license_key'=>['nullable','string','max:255'],'plan'=>['required','string','max:100'],
            'status'=>['required',Rule::in(['trial','active','suspended','expired'])],
            'starts_on'=>['nullable','date'],'expires_on'=>['nullable','date','after_or_equal:starts_on'],
            'licensed_facilities'=>['required','integer','min:1','max:10000'],'notes'=>['nullable','string','max:3000'],
        ]);
        $record=InstallationLicense::firstOrCreate(['hospital_id'=>$hospital->id]);
        $before=$record->toArray();
        $record->update($validated);
        $audit->record('commercial.license_updated',$record,$before,$record->fresh()->toArray(),actor:$request->user());
        return back()->with('success','Installation licence updated.');
    }

    public function updateIntegration(Request $request, IntegrationConfiguration $integration, AuditService $audit): RedirectResponse
    {
        $this->assertHospital($integration->hospital_id);
        abort_unless($request->user()->can('integrations.manage') || $request->user()->hasRole('superadmin'),403);
        $validated=$request->validate([
            'provider'=>['required','string','max:120'],
            'status'=>['required',Rule::in(['disabled','configured','active','error'])],
            'endpoint'=>['nullable','url','max:1000'],
            'account_reference'=>['nullable','string','max:255'],
            'notes'=>['nullable','string','max:3000'],
        ]);
        $before=$integration->only(['provider','status','configuration']);
        $integration->update([
            'provider'=>$validated['provider'],'status'=>$validated['status'],
            'configuration'=>Arr::whereNotNull([
                'endpoint'=>$validated['endpoint']??null,
                'account_reference'=>$validated['account_reference']??null,
                'notes'=>$validated['notes']??null,
            ]),
        ]);
        $audit->record('integrations.configuration_updated',$integration,$before,$integration->fresh()->only(['provider','status','configuration']),actor:$request->user());
        return back()->with('success','Integration configuration updated.');
    }

    public function recordBackup(Request $request, AuditService $audit): RedirectResponse
    {
        abort_unless($request->user()->can('backup-monitor.manage') || $request->user()->hasRole('superadmin'),403);
        $hospital=$this->currentHospital();
        $validated=$request->validate([
            'status'=>['required',Rule::in(['success','failed','warning'])],
            'source'=>['required','string','max:120'],
            'backup_reference'=>['nullable','string','max:500'],
            'size_bytes'=>['nullable','integer','min:0'],
            'backup_completed_at'=>['required','date'],
            'restore_verified_at'=>['nullable','date','after_or_equal:backup_completed_at'],
            'notes'=>['nullable','string','max:3000'],
        ]);
        $record=BackupMonitorEvent::create($validated+['hospital_id'=>$hospital->id,'recorded_by'=>$request->user()->id]);
        $audit->record('backup.monitor_event_recorded',$record,null,$record->toArray(),actor:$request->user());
        return back()->with('success','Backup monitoring event recorded.');
    }

    private function assertHospital(int $hospitalId): void
    {
        abort_unless($hospitalId===$this->currentHospital()->id,403);
    }
}
