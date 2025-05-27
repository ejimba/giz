<?php
/**
 * app/Http/Controllers/DebugConversationController.php
 * 
 * Debugging endpoints for conversation flow issues
 */

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Conversation;
use App\Services\Conversation\ConversationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DebugConversationController extends Controller
{
    public function __construct(
        private ConversationService $conversationService
    ) {}

    /**
     * Show current conversation state for a client
     */
    public function showState(Request $request): JsonResponse
    {
        $clientId = $request->input('client_id');
        
        if (!$clientId) {
            return response()->json(['error' => 'client_id is required'], 400);
        }
        
        $conversation = Conversation::where('client_id', $clientId)
            ->where('status', 'active')
            ->latest()
            ->first();
            
        if (!$conversation) {
            return response()->json(['error' => 'No active conversation found'], 404);
        }
        
        return response()->json([
            'conversation_id' => $conversation->id,
            'status' => $conversation->status,
            'current_step' => $conversation->metadata['step'] ?? 'unknown',
            'metadata' => $conversation->metadata,
            'current_prompt' => $conversation->currentPrompt?->title,
            'last_responses' => $conversation->responses()
                ->latest()
                ->limit(5)
                ->get(['content', 'created_at', 'metadata'])
        ]);
    }
    
    /**
     * Manually update conversation step (for testing)
     */
    public function updateStep(Request $request): JsonResponse
    {
        $conversationId = $request->input('conversation_id');
        $newStep = $request->input('step');
        
        if (!$conversationId || !$newStep) {
            return response()->json(['error' => 'conversation_id and step are required'], 400);
        }
        
        $conversation = Conversation::find($conversationId);
        
        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }
        
        $metadata = $conversation->metadata ?? [];
        $oldStep = $metadata['step'] ?? 'unknown';
        $metadata['step'] = $newStep;
        
        $conversation->update(['metadata' => $metadata]);
        
        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->id,
            'old_step' => $oldStep,
            'new_step' => $newStep,
            'metadata' => $conversation->metadata
        ]);
    }
    
    /**
     * Reset conversation to initial state
     */
    public function resetConversation(Request $request): JsonResponse
    {
        $clientId = $request->input('client_id');
        
        if (!$clientId) {
            return response()->json(['error' => 'client_id is required'], 400);
        }
        
        // Mark all active conversations as completed
        Conversation::where('client_id', $clientId)
            ->where('status', 'active')
            ->update(['status' => 'completed', 'completed_at' => now()]);
            
        return response()->json([
            'success' => true,
            'message' => 'All active conversations have been reset'
        ]);
    }
    
    /**
     * Test message processing
     */
    public function testMessage(Request $request): JsonResponse
    {
        $clientId = $request->input('client_id');
        $message = $request->input('message');
        
        if (!$clientId || !$message) {
            return response()->json(['error' => 'client_id and message are required'], 400);
        }
        
        $client = Client::find($clientId);
        
        if (!$client) {
            return response()->json(['error' => 'Client not found'], 404);
        }
        
        // Create a mock incoming message object
        $incoming = (object) ['message' => $message];
        
        // Get conversation state before processing
        $convBefore = Conversation::where('client_id', $clientId)
            ->where('status', 'active')
            ->latest()
            ->first();
        $stepBefore = $convBefore?->metadata['step'] ?? 'none';
        
        // Process the message
        try {
            $this->conversationService->processIncomingMessage($incoming, $client);
            
            // Get conversation state after processing
            $convAfter = Conversation::where('client_id', $clientId)
                ->where('status', 'active')
                ->latest()
                ->first();
            $stepAfter = $convAfter?->metadata['step'] ?? 'none';
            
            return response()->json([
                'success' => true,
                'conversation_id' => $convAfter?->id,
                'step_before' => $stepBefore,
                'step_after' => $stepAfter,
                'metadata' => $convAfter?->metadata,
                'step_changed' => $stepBefore !== $stepAfter
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error processing message',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}