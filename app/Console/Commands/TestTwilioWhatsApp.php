<?php

namespace App\Console\Commands;

use App\Models\OutgoingMessage;
use App\Services\TwilioService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestTwilioWhatsApp extends Command
{
    protected $signature = 'app:test-twilio-whatsapp {phone} {message?}';

    protected $description = 'Test sending a WhatsApp message via Twilio';

    public function handle()
    {
        $phone = $this->argument('phone');
        $message = $this->argument('message') ?? 'This is a test message from the GIZ application.';

        $this->info("Sending WhatsApp message to {$phone}...");

        try {
            // Create the outgoing message
            $outgoingMessage = OutgoingMessage::create([
                'type' => 'whatsapp',
                'phone' => $phone,
                'message' => $message,
                'status' => 'pending',
            ]);

            // Send the message
            $twilioService = app(TwilioService::class);
            $result = $twilioService->sendWhatsAppMessage($outgoingMessage->phone, $outgoingMessage->message);

            $this->info("Message sent successfully!");
            $this->info("Twilio Message SID: {$result->twilio_message_sid}");
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to send message: {$e->getMessage()}");
            Log::error("Failed to send test WhatsApp message", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return Command::FAILURE;
        }
    }
}
