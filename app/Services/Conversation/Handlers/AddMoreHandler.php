<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Conversation;
use App\Services\Conversation\Step;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\TwilioService;

class AddMoreHandler extends BaseStepHandler implements StepHandlerInterface
{
    public function __construct(TwilioService $twilio)
    {
        parent::__construct($twilio);
    }

    public function step(): string
    {
        return Step::ADD_MORE_PRODUCTS;
    }

    public function handle(Conversation $conv, string $message): void
    {
        $meta = $conv->metadata ?? [];
        if (empty($message)) {
            $cart = $meta['cart'] ?? [];
            $total = $meta['order_total'] ?? 0;
            $lines[] = "Current cart:\n";
            foreach ($cart as $i => $item) {
                $name = $item['product']['name'] . ' ' . $item['product']['type'];
                $green = $item['is_green'] ? ' (Green)' : '';
                $lines[] = ($i + 1) . ". {$name}{$green} - "
                        . "{$item['quantity']} x {$item['unit_price']} = {$item['total']}";
            }
            $lines[] = "\nTotal: {$total}\n";
            $msg = implode("\n", $lines). "\nWould you like to add another product?\n1. Yes\n2. No (proceed to checkout)\n";
            $this->twilio->sendWhatsAppMessage($conv->client->phone, $this->withNav($msg));
            $meta['prompt_sent'] = true;
            $conv->update(['metadata' => $meta]);
            return;
        }
        $choice = trim($message);
        if ($choice === '1') {
            unset(
                $meta['selected_product'],
                $meta['quantity'],
                $meta['unit_price'],
                $meta['green'],
                $meta['available_stock']
            );
            $meta['step'] = Step::PRODUCT_SELECTION;
            unset($meta['prompt_sent']);
            $this->transitionTo($conv, $meta['step'], $meta);
            return;
        }
        if ($choice === '2') {
            $meta['step'] = Step::CREDIT_SALE;
            unset($meta['prompt_sent']);
            $this->transitionTo($conv, $meta['step'], $meta);
            return;
        }
        $this->twilio->sendWhatsAppMessage(
            $conv->client->phone,
            $this->withNav("Invalid selection.\n1. Add another product\n2. Proceed to checkout\n")
        );
    }
}
