<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Prompt;
use App\Services\Conversation\ConversationService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestConversationFlow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-conversation-flow {--phone= : Optional phone number for the test client}'
        . ' {--reset : Reset the database and seed fresh data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the conversation flow by simulating a WhatsApp conversation';

    /**
     * The conversation service instance.
     *
     * @var \App\Services\ConversationService
     */
    protected $conversationService;

    /**
     * Create a new command instance.
     */
    public function __construct(ConversationService $conversationService)
    {
        parent::__construct();
        $this->conversationService = $conversationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('reset')) {
            $this->call('migrate:fresh');
            $this->call('db:seed');
            $this->info('Database reset and seeded successfully.');
        }

        // Get or create a test client
        $phone = $this->option('phone') ?? '+1234567890';
        $client = Client::firstOrCreate(
            ['phone' => $phone],
            [
                'name' => 'Test Client',
                'status' => 'active',
            ]
        );

        $this->info("Using test client: {$client->phone}");

        // Get the first prompt
        $prompt = Prompt::where('active', true)
            ->whereNull('parent_prompt_id')
            ->orderBy('order')
            ->first();

        if (!$prompt) {
            $this->error('No active prompts found in the database. Please run the seeder first.');
            return 1;
        }

        $this->info("Starting conversation with prompt: {$prompt->title}");

        // Simulate starting a conversation
        $message = $this->conversationService->handleIncomingMessage($client, 'Hello');
        $this->simulateConversation($client, $message);

        return 0;
    }

    /**
     * Simulate a conversation with the client
     *
     * @param Client $client
     * @param string $initialMessage
     * @return void
     */
    protected function simulateConversation(Client $client, string $initialMessage)
    {
        $this->info("\n📱 Conversation Simulator");
        $this->info("==============================");

        $this->info("\n🤖 Bot: {$initialMessage}");
        
        // Loop for the conversation
        while (true) {
            // Get user input
            $userMessage = $this->ask("\n👤 You");
            
            if (strtolower($userMessage) === 'exit' || strtolower($userMessage) === 'quit') {
                $this->info("\n👋 Ending conversation simulation.");
                break;
            }
            
            // Process the message through the conversation service
            $botResponse = $this->conversationService->handleIncomingMessage($client, $userMessage);
            $this->info("\n🤖 Bot: {$botResponse}");
            
            // Check if the conversation is complete
            $conversation = $client->activeConversation();
            if (!$conversation || $conversation->status !== 'active') {
                if ($conversation && $conversation->status === 'completed') {
                    $this->info("\n✅ Conversation completed successfully!");
                } elseif ($conversation && $conversation->status === 'abandoned') {
                    $this->info("\n❌ Conversation was abandoned.");
                }
                break;
            }
        }
        
        // Show conversation summary
        $this->showConversationSummary($client);
    }
    
    /**
     * Show a summary of the conversation
     *
     * @param Client $client
     * @return void
     */
    protected function showConversationSummary(Client $client)
    {
        $recentConversation = $client->conversations()->latest()->first();
        
        if (!$recentConversation) {
            $this->warn("No conversation found for this client.");
            return;
        }
        
        $this->info("\n📊 Conversation Summary");
        $this->info("==============================");
        $this->info("Conversation ID: {$recentConversation->id}");
        $this->info("Status: {$recentConversation->status}");
        $this->info("Started at: {$recentConversation->started_at}");
        $this->info("Completed at: " . ($recentConversation->completed_at ?: 'Not completed'));
        
        $responses = $recentConversation->responses;
        
        $this->info("\nResponses:");
        foreach ($responses as $response) {
            $prompt = $response->prompt;
            $this->info("\nPrompt: {$prompt->title}");
            $this->info("Question: {$prompt->content}");
            $this->info("Answer: {$response->content}");
        }
    }
}
