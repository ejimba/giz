<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\IncomingMessage;
use App\Services\Conversation\ConversationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessIncomingMessages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public $tries = 1;

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function handle(ConversationService $conversationService): void
    {
        $phoneNumber = $this->data['From'];
        $phoneNumber = str_replace('whatsapp:', '', $phoneNumber);
        $incomingMessage = IncomingMessage::create([
            'type' => 'whatsapp',
            'provider_id' => $this->data['MessageSid'] ?? null,
            'from' => $phoneNumber,
            'subject' => $this->data['Subject'] ?? null,
            'message' => $this->data['Body'] ?? '',
            'metadata' => $this->data,
        ]);
        $client = Client::firstOrCreate(['phone' => $phoneNumber]);
        $conversationService->processIncomingMessage($incomingMessage, $client);
    }
}
