<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\OutgoingMessage;
use App\Services\ConversationService;
use App\Services\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TwilioWebhookController extends Controller
{
    protected $twilioService;
    protected $conversationService;

    public function __construct(TwilioService $twilioService, ConversationService $conversationService)
    {
        $this->twilioService = $twilioService;
        $this->conversationService = $conversationService;
    }

    /**
     * Handle incoming WhatsApp messages from Twilio
     *
     * @param Request $request
     * @return Response
     */
    public function handleIncomingMessage(Request $request)
    {
        try {
            Log::info('Received webhook from Twilio', ['payload' => $request->all()]);
            
            // Process the incoming message
            $incomingMessage = $this->twilioService->processIncomingMessage($request->all());
            
            // Process the message through our conversation flow system
            $this->processConversationResponse($incomingMessage);
            
            return response()->noContent();
        } catch (\Exception $e) {
            Log::error('Error processing Twilio webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json(['error' => 'Failed to process message'], 500);
        }
    }
    
    /**
     * Process the incoming message through conversation flow system
     *
     * @param \App\Models\IncomingMessage $incomingMessage
     * @return void
     */
    protected function processConversationResponse($incomingMessage)
    {
        $client = Client::firstOrCreate([
            'phone' => $incomingMessage->from
        ]);
        $responseMessage = $this->conversationService->handleIncomingMessage(
            $client,
            $incomingMessage->message
        );
        $outgoingMessage = OutgoingMessage::create([
            'type' => 'whatsapp',
            'provider_id' => $incomingMessage->provider_id,
            'to' => $incomingMessage->from,
            'subject' => $incomingMessage->subject,
            'message' => $responseMessage,
            'processed_at' => null,
            'metadata' => [
                'conversation_id' => $incomingMessage->conversation_id,
                'client_id' => $client->id,
                'twilio_message_sid' => null,
            ],
        ]);
        $this->twilioService->sendWhatsAppMessage($outgoingMessage);
    }
    
    /**
     * Handle status callbacks from Twilio
     *
     * @param Request $request
     * @return Response
     */
    public function handleStatusCallback(Request $request)
    {
        try {
            $messageSid = $request->input('MessageSid');
            $messageStatus = $request->input('MessageStatus');
            
            Log::info('Received status callback from Twilio', [
                'message_sid' => $messageSid,
                'status' => $messageStatus,
                'payload' => $request->all(),
            ]);
            $outgoingMessage = OutgoingMessage::where('provider_id', $messageSid)->first();
            
            if ($outgoingMessage) {
                $outgoingMessage->metadata = array_merge((array) $outgoingMessage->metadata, [
                    'status_callback' => $request->all(),
                ]);
                $outgoingMessage->save();
            }
            return response()->noContent();
        } catch (\Exception $e) {
            Log::error('Error processing Twilio status callback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Failed to process status callback'], 500);
        }
    }
}
