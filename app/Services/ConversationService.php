<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Conversation;
use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Support\Facades\Log;

class ConversationService
{
    /**
     * Start a new conversation with a client
     *
     * @param Client $client
     * @param Prompt|null $startingPrompt
     * @param string|null $title
     * @return Conversation
     */
    public function startConversation(Client $client, ?Prompt $startingPrompt = null, ?string $title = null): Conversation
    {
        // Find the first active prompt if none provided
        if (!$startingPrompt) {
            $startingPrompt = Prompt::where('active', true)
                ->whereNull('parent_prompt_id')
                ->orderBy('order')
                ->first();
                
            if (!$startingPrompt) {
                throw new \Exception('No active prompts found to start conversation');
            }
        }

        // Create the conversation
        $conversation = Conversation::create([
            'client_id' => $client->id,
            'title' => $title ?? 'Conversation ' . now()->format('Y-m-d H:i'),
            'current_prompt_id' => $startingPrompt->id,
            'status' => 'active',
            'started_at' => now(),
            'metadata' => [
                'starting_prompt_id' => $startingPrompt->id,
            ],
        ]);

        Log::info('New conversation started', [
            'conversation_id' => $conversation->id,
            'client_id' => $client->id,
            'starting_prompt_id' => $startingPrompt->id,
        ]);

        return $conversation;
    }

    /**
     * Get the current prompt message for a conversation
     *
     * @param Conversation $conversation
     * @return string
     */
    public function getCurrentPromptMessage(Conversation $conversation): string
    {
        if (!$conversation->currentPrompt) {
            return 'No current prompt found for this conversation.';
        }

        return $conversation->currentPrompt->content;
    }

    /**
     * Process a response from a client
     *
     * @param Conversation $conversation
     * @param string $responseContent
     * @return Response
     */
    public function processResponse(Conversation $conversation, string $responseContent): Response
    {
        $client = $conversation->client;
        $prompt = $conversation->currentPrompt;

        if (!$prompt) {
            throw new \Exception('No current prompt found for conversation');
        }

        // Create the response
        $response = Response::create([
            'client_id' => $client->id,
            'prompt_id' => $prompt->id,
            'conversation_id' => $conversation->id,
            'content' => $responseContent,
            'received_at' => now(),
            'metadata' => [
                'prompt_type' => $prompt->type,
                'prompt_title' => $prompt->title
            ]
        ]);

        // Validate the response based on prompt type
        $this->validateResponse($response, $prompt);

        // Determine the next prompt
        $this->advanceConversation($conversation, $response);

        return $response;
    }

    /**
     * Advance the conversation based on the response
     *
     * @param Conversation $conversation
     * @param Response $response
     * @return Conversation
     */
    protected function advanceConversation(Conversation $conversation, Response $response): Conversation
    {
        $currentPrompt = $conversation->currentPrompt;
        $responseContent = trim(strtolower($response->content));

        // Handle yes/no type prompts with branching logic
        if ($currentPrompt->type === 'yes_no') {
            $yesResponses = ['yes', 'y', 'yeah', 'yep', 'sure', '1', 'ok', 'okay'];
            $noResponses = ['no', 'n', 'nope', 'nah', '0'];
            
            // For yes/no prompts, check for branch based on response
            if (in_array($responseContent, $yesResponses)) {
                // Yes response - follow the normal flow (next_prompt_id)
                if ($currentPrompt->next_prompt_id) {
                    $conversation->current_prompt_id = $currentPrompt->next_prompt_id;
                    $conversation->save();
                    return $conversation;
                }
            } else if (in_array($responseContent, $noResponses)) {
                // No response - look for a child prompt that might handle the "no" path
                $declinePrompt = $currentPrompt->childPrompts()->where('active', true)->first();
                if ($declinePrompt) {
                    $conversation->current_prompt_id = $declinePrompt->id;
                    $conversation->save();
                    return $conversation;
                } else {
                    // If no specific handling for "no", mark conversation as abandoned
                    $conversation->status = 'abandoned';
                    $conversation->save();
                    
                    Log::info('Conversation abandoned after declining', [
                        'conversation_id' => $conversation->id,
                        'client_id' => $conversation->client_id,
                    ]);
                    
                    return $conversation;
                }
            }
        }
        
        // Handle multiple choice prompts
        if ($currentPrompt->type === 'multiple_choice' && $currentPrompt->metadata && isset($currentPrompt->metadata['options'])) {
            // Store the selected option in the response metadata
            $options = $currentPrompt->metadata['options'];
            $selectedOption = null;
            
            // Check if response is a valid option number or matches an option value
            if (isset($options[$responseContent])) {
                $selectedOption = $options[$responseContent];
            } else {
                // Look for option value match
                $optionValues = array_map('strtolower', array_values($options));
                $key = array_search($responseContent, $optionValues);
                if ($key !== false) {
                    $selectedOption = $options[array_keys($options)[$key]];
                }
            }
            
            if ($selectedOption) {
                // Update response with the selected option
                $response->metadata = array_merge((array) $response->metadata, [
                    'selected_option' => $selectedOption,
                ]);
                $response->save();
            }
        }
        
        // Basic advancing - just go to the next prompt if available
        if ($currentPrompt->next_prompt_id) {
            $conversation->current_prompt_id = $currentPrompt->next_prompt_id;
            $conversation->save();
            return $conversation;
        }

        // Check if there are child prompts
        $childPrompts = $currentPrompt->childPrompts()->where('active', true)->orderBy('order')->get();
        if ($childPrompts->isNotEmpty()) {
            // For now, we'll simply take the first child prompt
            $nextPrompt = $childPrompts->first();
            $conversation->current_prompt_id = $nextPrompt->id;
            $conversation->save();
            return $conversation;
        }

        // If we get here, there are no more prompts, so mark the conversation as completed
        $conversation->status = 'completed';
        $conversation->completed_at = now();
        $conversation->save();

        Log::info('Conversation completed', [
            'conversation_id' => $conversation->id,
            'client_id' => $conversation->client_id,
        ]);

        return $conversation;
    }

    /**
     * Validate a response against the prompt type
     * 
     * @param Response $response
     * @param Prompt $prompt
     * @return bool
     */
    protected function validateResponse(Response $response, Prompt $prompt): bool
    {
        $content = trim(strtolower($response->content));
        $isValid = true;
        
        // Add validation based on prompt type
        switch ($prompt->type) {
            case 'yes_no':
                $validResponses = ['yes', 'y', 'yeah', 'yep', 'sure', '1', 'ok', 'okay', 'no', 'n', 'nope', 'nah', '0'];
                $isValid = in_array($content, $validResponses);
                break;
                
            case 'multiple_choice':
                if ($prompt->metadata && isset($prompt->metadata['options'])) {
                    $options = $prompt->metadata['options'];
                    // Check if response is a valid option number
                    $isValid = isset($options[$content]);
                    
                    if (!$isValid) {
                        // Check if response matches one of the option values
                        $optionValues = array_map('strtolower', array_values($options));
                        $isValid = in_array($content, $optionValues);
                    }
                }
                break;
        }
        
        if (!$isValid) {
            // For invalid responses, update the metadata to reflect this
            $response->metadata = array_merge((array) $response->metadata, [
                'validation' => [
                    'valid' => false,
                    'prompt_type' => $prompt->type
                ]
            ]);
            $response->save();
        }
        
        return $isValid;
    }
    
    /**
     * Handle an incoming message from a client
     *
     * @param Client $client
     * @param string $message
     * @return string Response message to send back
     */
    public function handleIncomingMessage(Client $client, string $message): string
    {
        // Check if client has an active conversation
        $conversation = $client->activeConversation();

        if (!$conversation) {
            // Start a new conversation
            $conversation = $this->startConversation($client);
            return $this->getCurrentPromptMessage($conversation);
        }

        // Process the response
        $response = $this->processResponse($conversation, $message);

        // If the conversation is still active, return the next prompt
        if ($conversation->status === 'active' && $conversation->currentPrompt) {
            return $this->getCurrentPromptMessage($conversation);
        }

        // If we get here, the conversation has ended
        return "Thank you for completing this conversation.";
    }
}
