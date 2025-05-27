<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Conversation;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\Conversation\Step;
use App\Services\TwilioService;

class CreditSaleHandler extends BaseStepHandler implements StepHandlerInterface
{
    public function __construct(TwilioService $twilio)
    {
        parent::__construct($twilio);
    }

    public function step(): string
    {
        return Step::CREDIT_SALE;
    }

    public function handle(Conversation $conv, string $message): void
    {
        $meta = $conv->metadata ?? [];
        if (empty($message)) {
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Is this a credit sale?\n1. Yes\n2. No\n")
            );
            $meta['prompt_sent'] = true;
            $conv->update(['metadata' => $meta]);
            return;
        }
        $choice = trim($message);
        if (!in_array($choice, ['1', '2'], true)) {
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Invalid selection. Reply 1 for Yes or 2 for No.\n")
            );
            return;
        }
        $isCredit = ($choice === '1');
        $meta['on_credit'] = $isCredit;
        $meta['deposit'] = $isCredit ? null : 0;
        $meta['step'] = $isCredit ? Step::DEPOSIT : Step::CONFIRMATION;
        unset($meta['prompt_sent']);
        $this->transitionTo($conv, $meta['step'], $meta);
    }
}
