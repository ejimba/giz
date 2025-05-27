<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Conversation;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\Conversation\Step;
use App\Services\TwilioService;

class CustomerErrorHandler extends BaseStepHandler implements StepHandlerInterface
{
    public function __construct(TwilioService $twilio)
    {
        parent::__construct($twilio);
    }

    public function step(): string
    {
        return Step::HANDLE_CUSTOMER_CREATION_ERROR;
    }

    public function handle(Conversation $conv, string $message): void
    {
        $meta = $conv->metadata ?? [];
       if (empty($message)) {
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav(
                    "Error creating customer.\n"
                  . "1. Try again with a different name\n"
                  . "2. Go back to customer selection\n"
                )
            );
            $meta['prompt_sent'] = true;
            $conv->update(['metadata' => $meta]);
            return;
        }
        $choice = trim($message);
        if ($choice === '1') {
            $meta['step'] = Step::NEW_CUSTOMER_NAME;
            unset($meta['prompt_sent']);
            $this->transitionTo($conv, $meta['step'], $meta);
        } elseif ($choice === '2') {
            $meta['step'] = Step::CUSTOMER_SELECTION;
            unset($meta['prompt_sent']);
            $this->transitionTo($conv, $meta['step'], $meta);
        } else {
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Invalid selection. Reply 1 to try again or 2 to go back.\n")
            );
            return;
        }
    }
}
