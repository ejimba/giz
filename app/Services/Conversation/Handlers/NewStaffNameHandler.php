<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Conversation;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\Conversation\Step;
use App\Services\TwilioService;

class NewStaffNameHandler extends BaseStepHandler implements StepHandlerInterface
{
    public function __construct(TwilioService $twilio)
    {
        parent::__construct($twilio);
    }

    public function step(): string
    {
        return Step::NEW_STAFF_NAME;
    }

    public function handle(Conversation $conv, string $message): void
    {
        $meta = $conv->metadata ?? [];
        
        if (empty($message)) {
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Please enter the staff member's name:")
            );
            return;
        }
        
        // Store the staff name
        $meta['new_staff_name'] = trim($message);
        $conv->update(['metadata' => $meta]);
        
        // Move to next step for phone number
        $this->twilio->sendWhatsAppMessage(
            $conv->client->phone,
            $this->withNav("Please enter the staff member's phone number:")
        );
        
        $meta['step'] = Step::NEW_STAFF_PHONE;
        $conv->update(['metadata' => $meta]);
    }
}
