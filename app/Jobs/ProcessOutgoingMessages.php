<?php

namespace App\Jobs;

use AfricasTalking\SDK\AfricasTalking;
use App\Models\OutgoingMessage;
use App\Services\TwilioService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessOutgoingMessages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

    protected $twilioService;

    public function __construct()
    {
        $this->twilioService = app(TwilioService::class);
    }

    public function handle(): void
    {
        $outgoingMessages = OutgoingMessage::whereNull('processed_at')->get();
        
        foreach ($outgoingMessages as $outgoingMessage) {
            try {
                if ($outgoingMessage->type === 'sms') {
                    $this->sendSms($outgoingMessage);
                } elseif ($outgoingMessage->type === 'email') {
                    $this->sendEmail($outgoingMessage);
                } elseif ($outgoingMessage->type === 'whatsapp') {
                    $this->sendWhatsApp($outgoingMessage);
                } else {
                    Log::warning('Unknown message type: ' . $outgoingMessage->type, ['message_id' => $outgoingMessage->id]);
                }
                
                $outgoingMessage->processed_at = now();
                $outgoingMessage->status = 'processed';
                $outgoingMessage->status_date = now();
                $outgoingMessage->save();
            } catch (\Exception $e) {
                Log::error('Failed to process outgoing message', [
                    'message_id' => $outgoingMessage->id,
                    'error' => $e->getMessage(),
                ]);
                
                $outgoingMessage->status = 'failed';
                $outgoingMessage->status_date = now();
                $outgoingMessage->metadata = array_merge((array) $outgoingMessage->metadata, [
                    'error' => $e->getMessage(),
                ]);
                $outgoingMessage->save();
            }
        }
    }

    private function sendSms($outgoingMessage)
    {
        $at = new AfricasTalking(config('services.africastalking.username'), config('services.africastalking.api_key'));
        $sms = $at->sms();
        $sms->send([
            'from' => config('services.africastalking.from'),
            'to' => $outgoingMessage->phone,
            'message' => $outgoingMessage->message,
        ]);
    }

    private function sendEmail($outgoingMessage)
    {
        Mail::html($outgoingMessage->message, function ($message) use ($outgoingMessage) {
            $message->to($outgoingMessage->user->email)->subject($outgoingMessage->subject);
        });
    }
    
    private function sendWhatsApp($outgoingMessage)
    {
        $this->twilioService->sendWhatsAppMessage($outgoingMessage);
    }
}
