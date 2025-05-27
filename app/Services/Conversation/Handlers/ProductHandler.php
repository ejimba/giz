<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Conversation;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\Conversation\Step;
use App\Services\EndevStovesService;
use App\Services\TwilioService;

class ProductHandler extends BaseStepHandler implements StepHandlerInterface
{
    protected EndevStovesService $endevStoves;

    public function __construct(TwilioService $twilio, EndevStovesService $endevStoves)
    {
        parent::__construct($twilio);
        $this->endevStoves = $endevStoves;
    }

    public function step(): string
    {
        return Step::PRODUCT_SELECTION;
    }

    public function handle(Conversation $conv, string $message): void
    {
        $meta = $conv->metadata ?? [];
        if (empty($message)) {
            $products = $this->endevStoves->fetchProducts();
            if (!$products) {
                $this->twilio->sendWhatsAppMessage(
                    $conv->client->phone,
                    $this->withNav("No products available at the moment.")
                );
                return;
            }
            $meta['products'] = $products;
            $meta['prompt_sent'] = true;
            $conv->update(['metadata' => $meta]);
            $list = "Select a product:\n";
            foreach ($products as $i => $p) {
                $list .= ($i + 1) . '. ' . $p['name'] . ' ' . $p['type'] . "\n";
            }
            $this->twilio->sendWhatsAppMessage($conv->client->phone, $this->withNav($list));
            return;
        }
        $sel = (int) trim($message);
        $products = $meta['products'] ?? [];
        if ($sel < 1 || $sel > count($products)) {
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Invalid selection. Please choose a number from the list.")
            );
            return;
        }
        $meta['selected_product'] = $products[$sel - 1];
        $meta['step'] = Step::GREEN_PRODUCT;
        unset($meta['prompt_sent']);
        $this->transitionTo($conv, $meta['step'], $meta);
    }
}
