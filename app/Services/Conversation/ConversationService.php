<?php

namespace App\Services\Conversation;

use App\Models\Client;
use App\Models\Conversation;
use App\Models\IncomingMessage;
use App\Models\Prompt;
use App\Models\Response;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use Exception;
use Illuminate\Support\Facades\Log;

class ConversationService
{
    private array $stepHandlers = [];

    public function __construct(private NavigationService $nav, array $handlers = [])
    {
        foreach ($handlers as $h)
        {
            $this->stepHandlers[$h->step()] = $h;
        }
    }

    public function processIncomingMessage(IncomingMessage $incoming, Client $client): void
    {
        try {
            $conv = $this->getOrCreateConversation($client);
            $body = trim($incoming->message);
            $this->storeInbound($conv, $body);
            if ($this->handleNavigationCommands($conv, $body)) {
                info('Handled navigation command', [
                    'conv_id' => $conv->id,
                    'step' => $conv->metadata['step'] ?? Step::INITIAL,
                    'body' => $body,
                ]);
                return;
            }
            $step = $conv->metadata['step'] ?? Step::INITIAL;
            $handler = $this->stepHandlers[$step] ?? null;
            if (!$handler) {
                info('No handler found for step', [
                    'step' => $step,
                    'available_handlers' => array_keys($this->stepHandlers),
                    'conv_id' => $conv->id,
                ]);
                $this->nav->resetToMain($conv);
                return;
            }
            $this->executeHandler($conv, $handler, $body);
        } catch (Exception $e) {
            Log::error('Critical error in processIncomingMessage', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'client_id' => $client->id
            ]);
            if (isset($conv)) {
                $this->nav->resetToMain($conv);
            }
        }
    }

    public function executeStep(Conversation $conv, string $step, string $simulatedMessage = ''): bool
    {
        $handler = $this->stepHandlers[$step] ?? null;
        if (!$handler) {
            Log::error("executeStep: no handler for step {$step}", [
                'conv_id' => $conv->id,
                'available_handlers' => array_keys($this->stepHandlers)
            ]);
            return false;
        }
        try {
            $metadata = $conv->metadata ?? [];
            $metadata['step'] = $step;
            $conv->metadata = $metadata;
            $conv->save();
            $this->executeHandler($conv, $handler, $simulatedMessage);
            return true;
        } catch (Exception $e) {
            Log::error('Error in executeStep', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'conv_id' => $conv->id,
                'step' => $step,
            ]);
            return false;
        }
    }

    private function executeHandler(Conversation $conv, StepHandlerInterface $handler, string $message): void
    {
        $handlerClass = get_class($handler);
        try {
            $conv->refresh();
            $handler->handle($conv, $message);
            $conv->refresh();
        } catch (Exception $e) {
            Log::error('Handler execution failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'conv_id' => $conv->id,
                'handler' => $handlerClass,
                'step' => $handler->step(),
            ]);
            $this->nav->resetToMain($conv);
            throw $e;
        }
    }

    private function handleNavigationCommands(Conversation $conv, string $body): bool
    {
        $step = $conv->metadata['step'] ?? Step::INITIAL;
        if ($step === Step::INITIAL) {
            return false;
        }
        if ($body === '00') {
            $this->nav->resetToMain($conv);
            return true;
        }
        if ($body === '0') {
            $previousStep = $this->nav->previous($step);
            if ($previousStep === Step::INITIAL) {
                $this->nav->resetToMain($conv);
                return true;
            }
            $this->executeStep($conv, $this->nav->previous($step));
            return true;
        }
        return false;
    }

    private function getOrCreateConversation(Client $client): Conversation
    {
        $existing = Conversation::where('client_id', $client->id)
            ->where('status', 'active')
            ->latest()
            ->first();
        if ($existing) {
            return $existing;
        }
        $prompt = Prompt::where('active', true)
            ->where('title', 'Sales Menu')
            ->whereJsonContains('metadata->is_sales_flow', true)
            ->first();
        if (!$prompt) {
            throw new Exception('Sales Menu prompt not found in database. Please ensure database is seeded correctly.');
        }
        $conversation = Conversation::create([
            'client_id'         => $client->id,
            'title'             => 'Sales Flow ' . now()->format('Y-m-d H:i'),
            'current_prompt_id' => $prompt->id,
            'status'            => 'active',
            'started_at'        => now(),
            'metadata'          => [
                'step' => Step::INITIAL,
                'is_sales_flow' => true,
                'created_at' => now()->toISOString(),
            ],
        ]);
        $this->executeStep($conversation, Step::INITIAL, '');
        return $conversation;
    }

    private function storeInbound(Conversation $conv, string $body): void
    {
        Response::create([
            'client_id'       => $conv->client_id,
            'conversation_id' => $conv->id,
            'prompt_id'       => $conv->current_prompt_id,
            'content'         => $body,
            'received_at'     => now(),
            'metadata'        => [
                'step' => $conv->metadata['step'] ?? Step::INITIAL,
                'prompt_title' => $conv->currentPrompt?->title,
                'message_type' => 'inbound',
            ],
        ]);
    }

    public function getRegisteredHandlers(): array
    {
        return array_keys($this->stepHandlers);
    }

    public function hasHandler(string $step): bool
    {
        return isset($this->stepHandlers[$step]);
    }

    public function getHandlerInfo(): array
    {
        $info = [];
        foreach ($this->stepHandlers as $step => $handler) {
            $info[$step] = [
                'class' => get_class($handler),
                'step' => $handler->step(),
            ];
        }
        return $info;
    }

    public function startConversation(Client $client): Conversation
    {
        $conversation = $this->getOrCreateConversation($client);
        info('New conversation started', [
            'client_id' => $client->id,
            'conversation_id' => $conversation->id,
        ]);
        return $conversation;
    }

    public function endConversation(Conversation $conv, string $reason = 'manual'): void
    {
        $metadata = $conv->metadata ?? [];
        $metadata['ended_reason'] = $reason;
        $metadata['ended_at'] = now()->toISOString();
        $conv->update([
            'status' => 'completed',
            'completed_at' => now(),
            'metadata' => $metadata
        ]);
    }
}