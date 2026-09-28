<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Hospital;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class AppointmentReminderService
{
    public function sendDue(?Hospital $hospital = null): array
    {
        $hospitals=$hospital ? collect([$hospital]) : Hospital::query()->where('status','active')->get();
        $result=['processed'=>0,'sent'=>0,'skipped'=>0,'failed'=>0];

        foreach($hospitals as $currentHospital){
            $templates=NotificationTemplate::where('hospital_id',$currentHospital->id)
                ->where('key','appointment_reminder')->where('is_active',true)->get();

            foreach($templates as $template){
                $target=now()->addMinutes($template->reminder_minutes_before);
                $appointments=Appointment::with(['patient','facility:id,name','department:id,name'])
                    ->where('hospital_id',$currentHospital->id)
                    ->whereIn('status',['scheduled','confirmed'])
                    ->whereBetween('starts_at',[$target->copy()->subMinutes(30),$target->copy()->addMinutes(30)])
                    ->get();

                foreach($appointments as $appointment){
                    $result['processed']++;
                    $fingerprint=hash('sha256',implode('|',[$template->id,$appointment->id,$appointment->starts_at?->toISOString()]));
                    if(NotificationDelivery::where('hospital_id',$currentHospital->id)->where('fingerprint',$fingerprint)->exists()){
                        $result['skipped']++;
                        continue;
                    }

                    $recipient=$template->channel==='email' ? $appointment->patient?->email : $appointment->patient?->phone;
                    $delivery=new NotificationDelivery([
                        'hospital_id'=>$currentHospital->id,
                        'notification_template_id'=>$template->id,
                        'appointment_id'=>$appointment->id,
                        'patient_id'=>$appointment->patient_id,
                        'channel'=>$template->channel,
                        'subject'=>$this->render($template->subject,$appointment,$currentHospital),
                        'body'=>$this->render($template->body,$appointment,$currentHospital),
                        'status'=>'queued',
                        'scheduled_for'=>now(),
                        'fingerprint'=>$fingerprint,
                    ]);
                    $delivery->recipient=$recipient;
                    $delivery->save();

                    if(blank($recipient)){
                        $delivery->update(['status'=>'skipped','error_message'=>'Patient has no recipient for this channel.','attempted_at'=>now()]);
                        $result['skipped']++;
                        continue;
                    }

                    try{
                        if($template->channel==='email'){
                            Mail::raw($delivery->body,function($message) use($delivery): void {
                                $message->to($delivery->recipient)->subject($delivery->subject ?: 'Appointment reminder');
                            });
                            $delivery->update(['status'=>'sent','provider'=>config('mail.default'),'attempted_at'=>now(),'sent_at'=>now()]);
                        } else {
                            $this->sendSms($delivery);
                        }
                        $result['sent']++;
                    }catch(Throwable $e){
                        report($e);
                        $delivery->update(['status'=>'failed','attempted_at'=>now(),'error_message'=>Str::limit($e->getMessage(),2000)]);
                        $result['failed']++;
                    }
                }
            }
        }

        return $result;
    }

    private function sendSms(NotificationDelivery $delivery): void
    {
        $url=config('services.sms_webhook.url');
        if(blank($url)){
            throw new \RuntimeException('SMS webhook is not configured for this installation.');
        }

        $request=Http::timeout(15)->acceptJson();
        if(filled(config('services.sms_webhook.token'))){
            $request=$request->withToken(config('services.sms_webhook.token'));
        }

        $response=$request->post($url,[
            'to'=>$delivery->recipient,
            'message'=>$delivery->body,
            'sender'=>config('services.sms_webhook.sender'),
            'reference'=>(string)$delivery->id,
        ]);
        $response->throw();

        $delivery->update([
            'status'=>'sent',
            'provider'=>'sms-webhook',
            'provider_reference'=>(string)($response->json('reference') ?? $response->json('id') ?? ''),
            'attempted_at'=>now(),
            'sent_at'=>now(),
        ]);
    }

    private function render(?string $text, Appointment $appointment, Hospital $hospital): ?string
    {
        if($text===null) return null;
        return strtr($text,[
            '{patient_name}'=>$appointment->patient?->full_name ?? 'Patient',
            '{hospital_name}'=>$hospital->display_name,
            '{appointment_date}'=>$appointment->starts_at?->format('Y-m-d') ?? '',
            '{appointment_time}'=>$appointment->starts_at?->format('H:i') ?? '',
            '{facility}'=>$appointment->facility?->name ?? '',
            '{department}'=>$appointment->department?->name ?? '',
        ]);
    }
}
