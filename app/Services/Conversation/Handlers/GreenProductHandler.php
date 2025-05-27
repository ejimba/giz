<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Conversation;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\Conversation\Step;
use App\Services\EndevStovesService;
use App\Services\TwilioService;

class GreenProductHandler extends BaseStepHandler implements StepHandlerInterface
{
    protected EndevStovesService $endevStoves;

    public function __construct(TwilioService $twilio, EndevStovesService  $endevStoves)
    {
        parent::__construct($twilio);
        $this->endevStoves = $endevStoves;
    }

    public function step(): string
    {
        return Step::GREEN_PRODUCT;
    }

    public function handle(Conversation $conv, string $message): void
    {
        $meta = $conv->metadata ?? [];
        if (empty($message)) {
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Is this a green product sale?\n1. Yes\n2. No\n")
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
        $isGreen = ($choice === '1');
        $product = $meta['selected_product'] ?? null;
        if (!$product) {
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Invalid product selection. Please select a product first.\n")
            );
            $meta['step'] = Step::PRODUCT_SELECTION;
            unset($meta['prompt_sent']);
            $this->transitionTo($conv, $meta['step'], $meta);
            return;
        }
        $stock = $this->endevStoves->checkStockAvailability($product['_id'], $isGreen);
        if (!$stock['available'] || ($stock['quantity'] ?? 0) <= 0) {
            $meta['step'] = Step::PRODUCT_SELECTION;
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                "Sorry, no stock available for that variant. Please choose another product."
            );
            $this->transitionTo($conv, $meta['step'], $meta);
            return;
        }
        $meta['green'] = $isGreen;
        $meta['available_stock'] = $stock['quantity'];
        $meta['step'] = Step::QUANTITY;
        unset($meta['prompt_sent']);
        $this->transitionTo($conv, $meta['step'], $meta);
    }
}
