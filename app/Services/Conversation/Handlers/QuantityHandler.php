<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Conversation;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\Conversation\Step;
use App\Services\TwilioService;
use Illuminate\Support\Facades\Log;

class QuantityHandler extends BaseStepHandler implements StepHandlerInterface
{
    public function __construct(TwilioService $twilio)
    {
        parent::__construct($twilio);
    }

    public function step(): string
    {
        return Step::QUANTITY;
    }

    public function handle(Conversation $conv, string $message): void
    {
        $meta = $conv->metadata ?? [];
        if (!isset($meta['available_stock'])) {
            $meta['step'] = Step::PRODUCT_SELECTION;
            $this->transitionTo($conv, $meta['step'], $meta);
            return;
        }
        if (empty($message)) {
            $available = $meta['available_stock'];
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Available stock: {$available} units\nEnter quantity:\n")
            );
            $meta['prompt_sent'] = true;
            $conv->update(['metadata' => $meta]);
            return;
        }
        $qty = (int) trim($message);
        $max = (int) $meta['available_stock'];
        if ($qty <= 0) {
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Quantity must be a positive number. Please try again.\n")
            );
            return;
        }
        if ($qty > $max) {
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Sorry, only {$max} units are available. Enter a smaller quantity.\n")
            );
            return;
        }
        $meta['quantity'] = $qty;
        $meta['step'] = Step::UNIT_PRICE;
        unset($meta['prompt_sent']);
        $this->transitionTo($conv, $meta['step'], $meta);
    }
}
