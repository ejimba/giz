<?php

namespace App\Http\Controllers;

use App\Models\OutgoingMessage;
use App\Services\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TwilioWebhookController extends Controller
{
    protected $twilioService;

    public function __construct(TwilioService $twilioService)
    {
        $this->twilioService = $twilioService;
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
            
            // Here you would typically process the message content
            // For now, we'll just send a simple acknowledgment response
            $this->sendAcknowledgmentResponse($incomingMessage);
            
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
     * Send a simple acknowledgment response
     * This is a placeholder for more complex message processing logic
     *
     * @param \App\Models\IncomingMessage $incomingMessage
     * @return void
     */
    protected function sendAcknowledgmentResponse($incomingMessage)
    {
        $outgoingMessage = OutgoingMessage::create([
            'type' => 'whatsapp',
            'phone' => $incomingMessage->from_number,
            'message' => "Thank you for your message. We have received: \"{$incomingMessage->message}\"",
            'status' => 'pending',
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
            
            // Update the message status in our database
            $outgoingMessage = OutgoingMessage::where('twilio_message_sid', $messageSid)->first();
            
            if ($outgoingMessage) {
                $outgoingMessage->status = $messageStatus;
                $outgoingMessage->status_date = now();
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
