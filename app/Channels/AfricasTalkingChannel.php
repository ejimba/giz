<?php

namespace App\Channels;

use AfricasTalking\SDK\AfricasTalking;
use Illuminate\Notifications\Notification;

class AfricasTalkingChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $at = new AfricasTalking(config('services.africastalking.username'), config('services.africastalking.api_key'));
        $sms = $at->sms();
        $result   = $sms->send([
            'from' => config('services.africastalking.from'),
            'to' => $notifiable->phone,
            'message' => $notification->toAfricasTalking($notifiable),
        ]);
    }
}
