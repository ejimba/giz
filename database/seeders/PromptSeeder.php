<?php

namespace Database\Seeders;

use App\Models\Prompt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PromptSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a simple linear conversation flow
        $welcomePrompt = Prompt::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Welcome Message',
            'content' => 'Welcome to our service! We would like to ask you a few questions to better understand your needs. Is that okay?',
            'type' => 'yes_no',
            'order' => 1,
            'active' => true,
        ]);
        
        $namePrompt = Prompt::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Ask for Name',
            'content' => 'Great! What is your name?',
            'type' => 'text',
            'order' => 2,
            'active' => true,
        ]);
        
        $servicePrompt = Prompt::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Ask about Service Interest',
            'content' => 'Nice to meet you! What service are you interested in? Please reply with one of these options: 1) Consultation, 2) Product Information, 3) Support',
            'type' => 'multiple_choice',
            'order' => 3,
            'active' => true,
            'metadata' => json_encode([
                'options' => [
                    '1' => 'Consultation',
                    '2' => 'Product Information',
                    '3' => 'Support'
                ]
            ]),
        ]);
        
        $contactTimePrompt = Prompt::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Ask for Contact Time',
            'content' => 'When is the best time to contact you? Please reply with one of these options: 1) Morning, 2) Afternoon, 3) Evening',
            'type' => 'multiple_choice',
            'order' => 4,
            'active' => true,
            'metadata' => json_encode([
                'options' => [
                    '1' => 'Morning',
                    '2' => 'Afternoon',
                    '3' => 'Evening'
                ]
            ]),
        ]);
        
        $thankYouPrompt = Prompt::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Thank You Message',
            'content' => 'Thank you for providing this information! Our team will reach out to you soon.',
            'type' => 'text',
            'order' => 5,
            'active' => true,
        ]);
        
        // Create a more complex conversation with branching
        $declinePrompt = Prompt::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Decline Response',
            'content' => 'I understand. If you change your mind, feel free to message us again. Have a great day!',
            'type' => 'text',
            'order' => 1,
            'active' => true,
            'parent_prompt_id' => $welcomePrompt->id,
        ]);
        
        // Set up the flow connections
        $welcomePrompt->next_prompt_id = $namePrompt->id;
        $welcomePrompt->save();
        
        $namePrompt->next_prompt_id = $servicePrompt->id;
        $namePrompt->save();
        
        $servicePrompt->next_prompt_id = $contactTimePrompt->id;
        $servicePrompt->save();
        
        $contactTimePrompt->next_prompt_id = $thankYouPrompt->id;
        $contactTimePrompt->save();
    }
}
