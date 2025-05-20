# WhatsApp Conversation Flow Management

This system allows you to create and manage structured conversations with clients via WhatsApp through Twilio, with support for additional channels in the future.

## Core Components

### Models

1. **Prompt**
   - Represents a question or message to send to clients
   - Can have different types: text, yes_no, multiple_choice
   - Supports conversation branching and sequential flows

2. **Response**
   - Records client responses to prompts
   - Tracks metadata like validation status and selected options

3. **Conversation**
   - Manages the state of an ongoing conversation
   - Tracks conversation progress and completion

### Services

- **ConversationService**: Handles conversation flow, response processing, and prompt progression
- **TwilioService**: Manages WhatsApp messaging via Twilio's API

## Using the System

### Creating Conversation Flows

1. Create prompts with appropriate types (text, yes_no, multiple_choice)
2. Link prompts together using next_prompt_id for linear flows
3. Use parent_prompt_id to create branching paths

### Testing Conversations

Use the test command to simulate a conversation:

```bash
# With a fresh database
php artisan app:test-conversation-flow --reset

# With an existing database and specific phone number
php artisan app:test-conversation-flow --phone=+1234567890
```

### WhatsApp Integration

The system automatically handles incoming WhatsApp messages through the TwilioWebhookController, which:

1. Receives incoming messages
2. Processes them through the ConversationService
3. Sends appropriate responses back to the client

## Extending the System

### Adding New Prompt Types

1. Add the new type to the Prompt model's type field
2. Implement validation logic in ConversationService::validateResponse
3. Add response handling logic in ConversationService::advanceConversation

### Adding New Channels

1. Create a new channel implementation in the Channels directory
2. Implement webhook controllers for the new channel
3. Update integration with the ConversationService
