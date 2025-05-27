<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Conversation;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\Conversation\Step;
use App\Services\EndevStovesService;
use App\Services\TwilioService;

class StockGreenHandler extends BaseStepHandler implements StepHandlerInterface
{
    protected EndevStovesService $endevStoves;

    public function __construct(TwilioService $twilio, EndevStovesService $endevStoves)
    {
        parent::__construct($twilio);
        $this->endevStoves = $endevStoves;
    }

    public function step(): string
    {
        return Step::STOCK_GREEN_PRODUCT;
    }

    public function handle(Conversation $conv, string $message): void
    {
        $meta = $conv->metadata ?? [];
        if (empty($message)) {
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Is this a green product?\n1. Yes\n2. No\n")
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
        $stock = $this->endevStoves->checkStockAvailability($product['_id'], $isGreen);
        $label = $isGreen ? 'Green' : 'Non-Green';
        $name = $product['name'] . ' ' . $product['type'];
        $body = "Stock availability for *{$name}* ({$label}):\n";
        if ($stock['available'] && ($stock['quantity'] ?? 0) > 0) {
            $body .= "Available: Yes ({$stock['quantity']} units)\n";
        } else {
            $body .= "Available: No – currently out of stock.\n";
        }
        $this->twilio->sendWhatsAppMessage(
            $conv->client->phone,
            $body
        );
        $meta['step'] = Step::STOCK_PRODUCT_SELECTION;
        unset($meta['prompt_sent']);
        $this->transitionTo($conv, $meta['step'], $meta);
    }
}
