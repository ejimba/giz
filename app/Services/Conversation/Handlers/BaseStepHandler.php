<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Client;
use App\Models\Conversation;
use App\Models\Prompt;
use App\Services\Conversation\ConversationService;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\TwilioService;
use Illuminate\Support\Facades\Log;

abstract class BaseStepHandler implements StepHandlerInterface
{
    protected TwilioService $twilio;
    protected ?ConversationService $conversationService = null;

    public function __construct(TwilioService $twilio)
    {
        $this->twilio = $twilio;
    }

    public function setConversationService(ConversationService $service): void
    {
        $this->conversationService = $service;
    }

    protected function convo(): ConversationService
    {
        return $this->conversationService ?? app(ConversationService::class);
    }

    protected function transitionTo(Conversation $conv, string $newStep, array $additionalMetadata = [], bool $executeImmediately = true): void
    {
        $metadata = $conv->metadata ?? [];
        $metadata['step'] = $newStep;
        $metadata = array_merge($metadata, $additionalMetadata);
        $conv->update(['metadata' => $metadata]);
        $this->updatePrompt($conv, $newStep);
        if ($executeImmediately) {
            $this->conversationService->executeStep($conv, $newStep, '');
        }
    }
    
    protected function updatePrompt(Conversation $conv, string $step): void
    {
        $promptTitles = [
            'staff_selection' => 'Staff Selection',
            'customer_selection' => 'Customer Selection',
            'date_selection' => 'Sale Date',
            'product_selection' => 'Product Selection',
            'quantity' => 'Quantity',
            'unit_price' => 'Unit Price',
            'green_product' => 'Green Product',
            'add_more_products' => 'Add More Products',
            'credit_sale' => 'Credit Sale',
            'deposit' => 'Deposit',
            'confirmation' => 'Confirmation',
            'stock_product_selection' => 'Stock Check Product Selection',
            'stock_green_product' => 'Stock Check Green Product',
            'new_customer_name' => 'New Customer Name',
            'new_customer_phone' => 'New Customer Phone',
        ];
        $title = $promptTitles[$step] ?? null;
        if ($title) {
            $prompt = Prompt::where('active', true)
                ->where('title', $title)
                ->first();
            if ($prompt) {
                $conv->update(['current_prompt_id' => $prompt->id]);
            }
        }
    }
    
    protected function sendError(Conversation $conv, string $message): void
    {
        $fullMessage = $message;
        if ($conv->metadata['step'] !== 'initial') {
            $fullMessage .= "\n\n0 - Go back\n00 - Main menu";
        }
        $this->twilio->sendWhatsAppMessage($conv->client->phone, $fullMessage);
    }
    
    protected function formatNumberedList(array $items, callable $formatter): string
    {
        $list = "";
        foreach ($items as $index => $item) {
            $list .= ($index + 1) . ". " . $formatter($item) . "\n";
        }
        return $list;
    }

    protected function withNav(string $message): string
    {
        return $message . "\n0 - Go back\n00 - Main menu";;
    }

    protected function startConversation(Client $client): void
    {
        $this->convo()->startConversation($client);
    }

    protected function endConversation(Conversation $conv, string $reason = 'manual'): void
    {
        $this->convo()->endConversation($conv, $reason);
    }
}