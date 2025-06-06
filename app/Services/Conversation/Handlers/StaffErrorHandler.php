<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Conversation;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\Conversation\NavigationService;
use App\Services\Conversation\Step;
use App\Services\TwilioService;

class StaffErrorHandler extends BaseStepHandler implements StepHandlerInterface
{
    protected NavigationService $nav;
    
    public function __construct(TwilioService $twilio, NavigationService $nav)
    {
        parent::__construct($twilio);
        $this->nav = $nav;
    }

    public function step(): string
    {
        return Step::HANDLE_STAFF_CREATION_ERROR;
    }

    public function handle(Conversation $conv, string $message): void
    {
        $meta = $conv->metadata ?? [];
        
        $errorMessage = $meta['staff_error'] ?? 'An unknown error occurred';
        
        if (empty($message)) {
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("❌ Error creating staff member: {$errorMessage}\n\n" .
                              "1. Try again\n" .
                              "2. Return to main menu\n\n" .
                              "Reply with 1 or 2")
            );
            return;
        }
        
        $option = trim($message);
        
        switch ($option) {
            case '1':
                // Try again - return to staff name step
                $meta['step'] = Step::NEW_STAFF_NAME;
                unset($meta['staff_error']);
                $conv->update(['metadata' => $meta]);
                $this->transitionTo($conv, Step::NEW_STAFF_NAME, $meta);
                break;
                
            case '2':
            default:
                // Return to main menu
                $this->nav->resetToMain($conv);
                break;
        }
    }
}
