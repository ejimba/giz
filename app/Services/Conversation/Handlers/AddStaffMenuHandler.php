<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Conversation;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\Conversation\Step;
use App\Services\TwilioService;

class AddStaffMenuHandler extends BaseStepHandler implements StepHandlerInterface
{
    public function __construct(TwilioService $twilio)
    {
        parent::__construct($twilio);
    }

    public function step(): string
    {
        return Step::ADD_STAFF_MENU;
    }

    public function handle(Conversation $conv, string $message): void
    {
        $meta = $conv->metadata ?? [];
        
        if (empty($message)) {
            $meta['prompt_sent'] = true;
            $conv->update(['metadata' => $meta]);
            
            $menuText = "Add New Staff\n\n" .
                       "Please enter the staff member's name:";
                       
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav($menuText)
            );
            
            // Move to the next step to collect staff name
            $meta['step'] = Step::NEW_STAFF_NAME;
            $conv->update(['metadata' => $meta]);
            return;
        }
        
        // If we get here, we're proceeding with the staff name
        $this->transitionTo($conv, Step::NEW_STAFF_NAME, $meta);
    }
}
