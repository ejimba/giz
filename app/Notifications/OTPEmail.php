<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OTPEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        //
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function getMessage(object $notifiable): string
    {
        return 'Your OTP is '.$notifiable->email_otp.'.';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('OTP')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line($this->getMessage($notifiable))
            ->line('Thank you!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'user' => $notifiable,
            'otp' => $notifiable->email_otp,
            'message' => $this->getMessage($notifiable),
        ];
    }
}
