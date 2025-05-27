<?php

namespace App\Services;

use App\Models\OutgoingMessage;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client as TwilioClient;

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

    public function sendWhatsAppMessage($phoneNumber, $messageContent, $metadata = [])
    {
        $outgoingMessage = OutgoingMessage::create([
            'type' => 'whatsapp',
            'to' => $phoneNumber,
            'message' => $messageContent,
            'processed_at' => null,
            'metadata' => $metadata,
        ]);
        $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
        $to = 'whatsapp:' . $phoneNumber;
        $from = 'whatsapp:' . $this->fromNumber;
        if (str_replace('whatsapp:', '', $to) === str_replace('whatsapp:', '', $from)) {
            Log::warning('Prevented sending WhatsApp message to same number as From number', [
                'to' => $to,
                'from' => $from,
                'message' => $messageContent
            ]);
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
        return $outgoingMessage;
    }

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
