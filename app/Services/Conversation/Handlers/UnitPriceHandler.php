<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Conversation;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\Conversation\Step;
use App\Services\TwilioService;

class UnitPriceHandler extends BaseStepHandler implements StepHandlerInterface
{
    public function __construct(TwilioService $twilio)
    {
        parent::__construct($twilio);
    }

    public function step(): string
    {
        return Step::UNIT_PRICE;
    }

    public function handle(Conversation $conv, string $message): void
    {
        $meta = $conv->metadata ?? [];
        if (empty($message)) {
            $default = $meta['selected_product']['price'] ?? '';
            $prompt = 'Enter unit price' . ($default ? " (default: {$default})" : '') . ":\n";
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav($prompt)
            );
            return;
        }
        $price = (float) trim($message);
        if ($price <= 0) {
            $price = (float) ($meta['selected_product']['price'] ?? 0);
            if ($price <= 0) {
                $this->twilio->sendWhatsAppMessage(
                    $conv->client->phone,
                    $this->withNav("Unit price must be a positive number. Please try again.\n")
                );
                return;
            }
        }
        $itemTotal = $price * ($meta['quantity'] ?? 0);
        $cart   = $meta['cart'] ?? [];
        $cart[] = [
            'product' => $meta['selected_product'],
            'quantity' => $meta['quantity'],
            'unit_price' => $price,
            'is_green' => $meta['green'] ?? false,
            'total' => $itemTotal,
        ];
        $orderTotal = array_sum(array_column($cart, 'total'));
        $meta['cart'] = $cart;
        $meta['order_total'] = $orderTotal;
        $meta['step'] = Step::ADD_MORE_PRODUCTS;
        unset($meta['prompt_sent']);
        $this->transitionTo($conv, $meta['step'], $meta);
    }
}
