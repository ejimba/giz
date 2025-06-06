<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Conversation;
use App\Models\Prompt;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\Conversation\Step;
use App\Services\TwilioService;

class InitialHandler extends BaseStepHandler implements StepHandlerInterface
{
    public function __construct(TwilioService $twilio)
    {
        parent::__construct($twilio);
    }

    public function step(): string
    {
        return Step::INITIAL;
    }

    public function handle(Conversation $conv, string $message): void
    {
        if (empty($message)) {
            // $this->sendMenu($conv);
            $metadata = $conv->metadata ?? [];
            $metadata['prompt_sent'] = true;
            $conv->update(['metadata' => $metadata]);
            return;
        }
        $option = trim($message);
        switch ($option) {
            case '1':
                $this->transitionTo($conv, Step::STAFF_SELECTION, [
                    'flow_type' => 'sale',
                    'prompt_sent' => true,
                    'step' => Step::STAFF_SELECTION,
                ]);
                break;
            case '2':
                $this->transitionTo($conv, Step::STOCK_PRODUCT_SELECTION, [
                    'flow_type' => 'stock_check',
                    'prompt_sent' => true,
                    'step' => Step::STOCK_PRODUCT_SELECTION,
                ]);
                break;
            case '3':
                $this->transitionTo($conv, Step::ADD_CUSTOMER_MENU, [
                    'flow_type' => 'add_customer',
                    'prompt_sent' => true,
                    'step' => Step::ADD_CUSTOMER_MENU,
                ]);
                break;
            case '4':
                $this->transitionTo($conv, Step::ADD_STAFF_MENU, [
                    'flow_type' => 'add_staff',
                    'prompt_sent' => true,
                    'step' => Step::ADD_STAFF_MENU,
                ]);
                break;
            default:
                $this->sendMenu($conv);
                break;
        }
    }

    private function sendMenu(Conversation $conv): void
    {       
        $menuText = "Welcome! Please select an option:\n\n" .
                   "1. Record a Sale\n" .
                   "2. Check Stock Availability\n" .
                   "3. Add New Customer\n" .
                   "4. Add New Staff\n\n" .
                   "Reply with a number 1-4";
                   
        $this->twilio->sendWhatsAppMessage(
            $conv->client->phone,
            $menuText
        );
    }
}