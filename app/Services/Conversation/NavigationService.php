<?php

namespace App\Services\Conversation;

use App\Models\Conversation;
use App\Models\Prompt;
use App\Services\TwilioService;

class NavigationService
{
    public function __construct(private TwilioService $twilio) {}

    public function previous(string $current): string
    {
        return Step::getNavigationMap()[$current] ?? Step::INITIAL;
    }

    public function resetToMain(Conversation $conv): void
    {
        info('Resetting conversation to main menu', [
            'conv_id' => $conv->id,
            'current_step' => $conv->metadata['step'] ?? Step::INITIAL,
        ]);
        $prompt = Prompt::where('active', true)
            ->where('title', 'Sales Menu')
            ->whereJsonContains('metadata->is_sales_flow', true)
            ->first();
        if (!$prompt) {
            $conv->update(['metadata->step' => Step::INITIAL]);
            return;
        }
        $conv->update([
            'current_prompt_id' => $prompt->id,
            'metadata'          => ['step' => Step::INITIAL],
        ]);
        $this->twilio->sendWhatsAppMessage($conv->client->phone, $prompt->content);
    }
}
