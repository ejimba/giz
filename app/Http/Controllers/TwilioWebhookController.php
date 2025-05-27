<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessIncomingMessages;
use App\Models\OutgoingMessage;
use Illuminate\Http\Request;

class TwilioWebhookController extends Controller
{
    public function handleIncomingMessage(Request $request)
    {
        ProcessIncomingMessages::dispatch($request->all());
        return response()->noContent();
    }
    
    public function handleStatusCallback(Request $request)
    {
        $messageSid = $request->input('MessageSid');
        $outgoingMessage = OutgoingMessage::where('provider_id', $messageSid)->first();
        if ($outgoingMessage) {
            $outgoingMessage->metadata = array_merge((array) $outgoingMessage->metadata, [
                'status_callback' => $request->all(),
            ]);
            $outgoingMessage->save();
        }
        return response()->noContent();
    }
}
