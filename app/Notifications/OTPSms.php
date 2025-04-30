<?php

namespace App\Notifications;

use App\Channels\AfricasTalkingChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OTPSms extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return [AfricasTalkingChannel::class];
    }

    public function toAfricasTalking(object $notifiable): string
    {
        return 'Your OTP is '.$notifiable->phone_otp.'. Thank you';
    }

    public function toArray(object $notifiable): array
    {
        return [
            'user' => $notifiable,
            'otp' => $notifiable->phone_otp,
            'message' => $this->toAfricasTalking($notifiable),
        ];
    }
}
