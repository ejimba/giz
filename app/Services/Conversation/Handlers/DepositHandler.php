<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Conversation;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\Conversation\Step;
use App\Services\TwilioService;

class DepositHandler extends BaseStepHandler implements StepHandlerInterface
{
    public function __construct(TwilioService $twilio)
    {
        parent::__construct($twilio);
    }

    public function step(): string
    {
        return Step::DEPOSIT;
    }

    public function handle(Conversation $conv, string $message): void
    {
        $meta = $conv->metadata ?? [];
        if (empty($message)) {
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Enter deposit amount:\n")
            );
            $meta['prompt_sent'] = true;
            $conv->update(['metadata' => $meta]);
            return;
        }
        $deposit = (float) trim($message);
        $orderTotal = (float) ($meta['order_total'] ?? 0);
        if ($deposit < 0) {
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Deposit cannot be negative. Please enter a valid amount.\n")
            );
            return;
        }
        if ($orderTotal > 0 && $deposit > $orderTotal) {
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Deposit cannot exceed the total ({$orderTotal}). Please enter a smaller amount.\n")
            );
            return;
        }
        $meta['deposit'] = $deposit;
        $meta['step'] = Step::CONFIRMATION;
        unset($meta['prompt_sent']);
        $this->transitionTo($conv, $meta['step'], $meta);
    }
}
