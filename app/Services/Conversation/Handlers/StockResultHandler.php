<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Conversation;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\Conversation\Step;
use App\Services\TwilioService;

class StockResultHandler extends BaseStepHandler implements StepHandlerInterface
{
    public function __construct(TwilioService $twilio)
    {
        parent::__construct($twilio);
    }

    public function step(): string
    {
        return Step::STOCK_CHECK_RESULT;
    }

    public function handle(Conversation $conv, string $message): void
    {
        $meta = $conv->metadata ?? [];
        $choice = trim($message);
        if ($choice === '1') {
            $meta['step'] = Step::STOCK_PRODUCT_SELECTION;
            unset($meta['prompt_sent'], $meta['selected_product']);
            $this->transitionTo($conv, $meta['step'], $meta);
            return;
        }
        $this->endConversation($conv, 'completed');
        $this->startConversation($conv->client);
    }
}
