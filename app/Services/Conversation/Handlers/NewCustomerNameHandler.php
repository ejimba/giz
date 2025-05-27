<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Conversation;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\Conversation\Step;
use App\Services\TwilioService;

class NewCustomerNameHandler extends BaseStepHandler implements StepHandlerInterface
{
    public function __construct(TwilioService $twilio)
    {
        parent::__construct($twilio);
    }

    public function step(): string
    {
        return Step::NEW_CUSTOMER_NAME;
    }

    public function handle(Conversation $conv, string $message): void
    {
        $meta = $conv->metadata ?? [];
        if (empty($message)) {
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Please enter the customer's name:\n")
            );
            $meta['prompt_sent'] = true;
            $conv->update(['metadata' => $meta]);
            return;
        }
        $name = trim($message);
        if ($name === '') {
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Name cannot be empty. Please enter the customer's name:\n")
            );
            return;
        }
        $meta['new_customer_name'] = $name;
        $meta['step'] = Step::NEW_CUSTOMER_PHONE;
        unset($meta['prompt_sent']);
        $this->transitionTo($conv, $meta['step'], $meta);
    }
}
