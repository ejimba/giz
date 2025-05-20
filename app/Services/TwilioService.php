<?php

namespace App\Services;

use App\Models\IncomingMessage;
use App\Models\OutgoingMessage;
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
     * Send a WhatsApp message via Twilio
     *
     * @param OutgoingMessage $message
     * @return OutgoingMessage
     */
    public function sendWhatsAppMessage(OutgoingMessage $message)
    {
        try {
            $to = 'whatsapp:' . $message->to;
            $from = 'whatsapp:' . $this->fromNumber;

            $response = $this->client->messages->create($to, [
                'from' => $from,
                'body' => $message->message,
            ]);

            $message->provider_id = $response->sid;
            $message->processed_at = now();
            $message->metadata = array_merge((array) $message->metadata, [
                'twilio_response' => $response,
            ]);
            $message->save();

            Log::info('WhatsApp message sent successfully', [
                'message_id' => $message->id,
                'twilio_sid' => $response->sid,
            ]);

            return $message;
        } catch (Exception $e) {
            Log::error('Failed to send WhatsApp message', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            $message->status = 'failed';
            $message->status_date = now();
            $message->metadata = array_merge((array) $message->metadata, [
                'error' => $e->getMessage(),
            ]);
            $message->save();

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
