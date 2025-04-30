<?php

namespace App\Observers;

use App\Models\OutgoingMessage;

class OutgoingMessageObserver
{
    public function creating(OutgoingMessage $outgoingMessage)
    {
        if ($outgoingMessage->type === 'email') {
            $outgoingMessage->email = $outgoingMessage->user->email;
        }
        if ($outgoingMessage->type === 'sms') {
            $outgoingMessage->phone = $outgoingMessage->user->phone;
        }
    }
}
