<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\Conversation;
use App\Models\IncomingMessage;
use App\Services\ConversationService;
use App\Services\SalesConversationService;
use App\Services\TwilioService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $incomingMessageId;
    public $tries = 3;
    public $backoff = 30; // 30 seconds between retry attempts

    /**
     * Create a new job instance.
     */
    public function __construct($incomingMessageId)
    {
        $this->incomingMessageId = $incomingMessageId;
    }

    /**
     * Execute the job.
     */
    public function handle(
        ConversationService $conversationService,
        SalesConversationService $salesConversationService,
        TwilioService $twilioService
    ): void
    {
        try {
            // Retrieve the saved incoming message
            $incomingMessage = IncomingMessage::findOrFail($this->incomingMessageId);
            
            // Get or create client
            $client = Client::firstOrCreate([
                'phone' => $incomingMessage->from
            ]);
            
            // Check if there's an active sales conversation
            $salesConversation = Conversation::where('client_id', $client->id)
                ->where('status', 'active')
                ->whereJsonContains('metadata->is_sales_flow', true)
                ->latest()
                ->first();
                
            if ($salesConversation) {
                // Continue existing sales conversation
                $salesConversationService->processResponse($salesConversation, $incomingMessage->message);
                return;
            }
            
            // Check if this is a sales initiation message
            if (trim(strtolower($incomingMessage->message)) === '1') {
                // Start a new sales conversation
                $salesConversationService->startSalesConversation($client);
                return;
            }
            
            // If not sales-related, handle with regular conversation flow
            $responseMessage = $conversationService->handleIncomingMessage(
                $client,
                $incomingMessage->message
            );
            
            // Send the response message
            $twilioService->sendWhatsAppMessage($client->phone, $responseMessage);
            
        } catch (\Exception $e) {
            Log::error('Error processing WhatsApp message', [
                'message_id' => $this->incomingMessageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // If we've exceeded retries, notify someone
            if ($this->attempts() >= $this->tries) {
                // This could send an alert to admin, log to monitoring, etc.
                Log::critical('Failed to process WhatsApp message after multiple attempts', [
                    'message_id' => $this->incomingMessageId
                ]);
            }
            
            throw $e; // Rethrow to trigger retry mechanism
        }
    }
}
