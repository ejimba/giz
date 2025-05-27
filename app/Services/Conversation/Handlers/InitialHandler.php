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
            default:
                $this->sendMenu($conv);
                break;
        }
    }

    private function sendMenu(Conversation $conv): void
    {
        $menuPrompt = Prompt::where('active', true)
            ->where('title', 'Sales Menu')
            ->first();
            
        $menuText = $menuPrompt ? $menuPrompt->content : 
                   "Welcome! Please select an option:\n\n" .
                   "1. Record a Sale\n" .
                   "2. Check Stock Availability\n\n" .
                   "Reply with 1 or 2";
                   
        $this->twilio->sendWhatsAppMessage(
            $conv->client->phone,
            $menuText
        );
    }
}