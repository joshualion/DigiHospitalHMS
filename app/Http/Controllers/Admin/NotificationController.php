<?php

namespace App\Http\Controllers\Admin;

use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use App\Services\AppointmentReminderService;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends FoundationController
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('notifications.view') || $request->user()->hasRole('superadmin'),403);
        $hospital=$this->currentHospital();

        $this->ensureDefaultTemplates($hospital->id);

        return Inertia::render('Admin/Notifications/Index',[
            'templates'=>NotificationTemplate::where('hospital_id',$hospital->id)->orderBy('channel')->get(),
            'deliveries'=>NotificationDelivery::with(['patient:id,hospital_number,first_name,middle_name,last_name'])
                ->where('hospital_id',$hospital->id)->latest()->paginate(30),
            'smsConfigured'=>filled(config('services.sms_webhook.url')),
            'mailDriver'=>config('mail.default'),
        ]);
    }

    public function update(Request $request, NotificationTemplate $template, AuditService $audit): RedirectResponse
    {
        $this->assertHospital($template->hospital_id);
        abort_unless($request->user()->can('notifications.manage') || $request->user()->hasRole('superadmin'),403);
        $validated=$request->validate([
            'name'=>['required','string','max:255'],
            'subject'=>['nullable','string','max:255'],
            'body'=>['required','string','max:5000'],
            'reminder_minutes_before'=>['required','integer','min:30','max:10080'],
            'is_active'=>['boolean'],
        ]);
        $before=$template->toArray();
        $template->update($validated);
        $audit->record('notifications.template_updated',$template,$before,$template->fresh()->toArray(),actor:$request->user());
        return back()->with('success','Notification template updated.');
    }

    public function run(Request $request, AppointmentReminderService $service, AuditService $audit): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.send') || $request->user()->hasRole('superadmin'),403);
        $hospital=$this->currentHospital();
        $result=$service->sendDue($hospital);
        $audit->record('notifications.reminders_run',null,null,$result,hospital:$hospital,actor:$request->user());
        return back()->with('success',"Reminder run complete: {$result['sent']} sent, {$result['skipped']} skipped, {$result['failed']} failed.");
    }

    private function ensureDefaultTemplates(int $hospitalId): void
    {
        NotificationTemplate::firstOrCreate(
            ['hospital_id'=>$hospitalId,'key'=>'appointment_reminder','channel'=>'email'],
            [
                'name'=>'Appointment reminder email',
                'subject'=>'Appointment reminder from {hospital_name}',
                'body'=>"Hello {patient_name},\n\nThis is a reminder of your appointment at {hospital_name} on {appointment_date} at {appointment_time}.\nFacility: {facility}\nDepartment: {department}\n\nPlease contact the hospital if you need to reschedule.",
                'reminder_minutes_before'=>1440,
                'is_active'=>true,
            ]
        );
        NotificationTemplate::firstOrCreate(
            ['hospital_id'=>$hospitalId,'key'=>'appointment_reminder','channel'=>'sms'],
            [
                'name'=>'Appointment reminder SMS',
                'body'=>'Reminder: {patient_name}, your appointment at {hospital_name} is {appointment_date} {appointment_time}. {facility} {department}.',
                'reminder_minutes_before'=>1440,
                'is_active'=>false,
            ]
        );
    }

    private function assertHospital(int $hospitalId): void
    {
        abort_unless($hospitalId===$this->currentHospital()->id,403);
    }
}
