<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppMessage;
use App\Models\IncomingMessage;
use App\Models\OutgoingMessage;
use App\Services\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TwilioWebhookController extends Controller
{
    protected $twilioService;

    public function __construct(TwilioService $twilioService) {
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
            
            // Process and save the incoming message
            $incomingMessage = $this->twilioService->processIncomingMessage($request->all());
            
            // Dispatch a job to process the message asynchronously
            ProcessWhatsAppMessage::dispatch($incomingMessage->id);
            
            // Return immediately with a 200 response to acknowledge receipt
            return response()->noContent();
        } catch (\Exception $e) {
            Log::error('Error processing Twilio webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Even on error, return 200 to Twilio to prevent retries
            // We'll handle retry logic in our queue
            return response()->noContent();
        }
    }
    
    // The processing of messages is now handled by the ProcessWhatsAppMessage job
    
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
