<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Conversation;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\Conversation\Step;
use App\Services\TwilioService;

class AddCustomerMenuHandler extends BaseStepHandler implements StepHandlerInterface
{
    public function __construct(TwilioService $twilio)
    {
        parent::__construct($twilio);
    }

    public function step(): string
    {
        return Step::ADD_CUSTOMER_MENU;
    }

    public function handle(Conversation $conv, string $message): void
    {
        $meta = $conv->metadata ?? [];
        
        if (empty($message)) {
            $meta['prompt_sent'] = true;
            $conv->update(['metadata' => $meta]);
            
            $menuText = "Add New Customer\n\n" .
                       "Please enter the customer's name:";
                       
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav($menuText)
            );
            
            // Move to the next step to collect customer name
            $meta['step'] = Step::NEW_CUSTOMER_NAME;
            $conv->update(['metadata' => $meta]);
            return;
        }
        
        // If we get here, we're proceeding with the customer name
        $this->transitionTo($conv, Step::NEW_CUSTOMER_NAME, $meta);
    }
}
