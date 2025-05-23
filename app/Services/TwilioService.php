<?php

namespace App\Services;

use App\Models\IncomingMessage;
use App\Models\OutgoingMessage;
use App\Models\Client;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client as TwilioClient;
use Exception;

class TwilioService
{
    protected $client;
    protected $fromNumber;

    public function __construct()
    {
        $this->client = new TwilioClient(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );
        $this->fromNumber = config('services.twilio.whatsapp_number');
    }

    /**
     * Process an incoming WhatsApp message from Twilio
     *
     * @param array $payload
     * @return IncomingMessage
     */
    public function processIncomingMessage(array $payload)
    {
        Log::info('Processing incoming WhatsApp message', ['payload' => $payload]);
        $phoneNumber = $payload['From'] ?? null;
        if (!$phoneNumber) {
            throw new Exception('Phone number not found in payload');
        }
        $phoneNumber = str_replace('whatsapp:', '', $phoneNumber);
        $incomingMessage = IncomingMessage::create([
            'type' => 'whatsapp',
            'provider_id' => $payload['MessageSid'] ?? null,
            'from' => $phoneNumber,
            'subject' => $payload['Subject'] ?? null,
            'message' => $payload['Body'] ?? '',
            'metadata' => $payload,
        ]);

        return $incomingMessage;
    }

    /**
     * Send a WhatsApp message directly by phone number and content
     *
     * @param string $phoneNumber
     * @param string $messageContent
     * @param array $metadata
     * @return OutgoingMessage
     */
    public function sendWhatsAppMessage($phoneNumber, $messageContent, $metadata = [])
    {
        try {
            // Create an OutgoingMessage record
            $outgoingMessage = OutgoingMessage::create([
                'type' => 'whatsapp',
                'to' => $phoneNumber,
                'message' => $messageContent,
                'processed_at' => null,
                'metadata' => $metadata,
            ]);
            
            // Clean the phone number (remove any non-numeric characters except +)
            $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
            
            $to = 'whatsapp:' . $phoneNumber;
            $from = 'whatsapp:' . $this->fromNumber;
            
            // Check if To and From numbers are the same - Twilio doesn't allow this
            if (str_replace('whatsapp:', '', $to) === str_replace('whatsapp:', '', $from)) {
                Log::warning('Prevented sending WhatsApp message to same number as From number', [
                    'to' => $to,
                    'from' => $from,
                    'message' => $messageContent
                ]);
                
                // Mark as processed to prevent retries, but with special status
                $outgoingMessage->processed_at = now();
                $outgoingMessage->metadata = array_merge((array) $outgoingMessage->metadata, [
                    'status' => 'skipped_same_number',
                    'message' => 'Cannot send WhatsApp message from and to the same number'
                ]);
                $outgoingMessage->save();
                
                return $outgoingMessage;
            }

            $response = $this->client->messages->create($to, [
                'from' => $from,
                'body' => $messageContent,
            ]);

            $outgoingMessage->provider_id = $response->sid;
            $outgoingMessage->processed_at = now();
            $outgoingMessage->metadata = array_merge((array) $outgoingMessage->metadata, [
                'twilio_response' => $response,
            ]);
            $outgoingMessage->save();

            Log::info('WhatsApp message sent successfully', [
                'message_id' => $outgoingMessage->id,
                'twilio_sid' => $response->sid,
            ]);

            return $outgoingMessage;
        } catch (Exception $e) {
            Log::error('Failed to send WhatsApp message', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);
            
            // If we created an outgoing message record, update it with the error
            if (isset($outgoingMessage)) {
                $outgoingMessage->processed_at = now();
                $outgoingMessage->metadata = array_merge((array) $outgoingMessage->metadata, [
                    'error' => $e->getMessage(),
                ]);
                $outgoingMessage->save();
            }
            
            throw $e;
        }
    }

    /**
     * Extract media files from Twilio payload
     *
     * @param array $payload
     * @return array
     */
    protected function extractMediaFromPayload(array $payload)
    {
        $media = [];
        $numMedia = (int) ($payload['NumMedia'] ?? 0);

        for ($i = 0; $i < $numMedia; $i++) {
            $mediaUrl = $payload["MediaUrl$i"] ?? null;
            $contentType = $payload["MediaContentType$i"] ?? null;

            if ($mediaUrl && $contentType) {
                $media[] = [
                    'url' => $mediaUrl,
                    'content_type' => $contentType,
                ];
            }
        }

        return $media;
    }
}
