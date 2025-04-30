# Twilio WhatsApp Integration

This document provides instructions for setting up and using the Twilio WhatsApp integration in the GIZ application.

## Overview

The integration allows the application to:
- Receive incoming WhatsApp messages from clients via Twilio
- Store messages in the database
- Process messages based on content
- Send outgoing WhatsApp messages to clients

## Database Structure

The integration uses the following tables:
- `clients` - Stores information about WhatsApp clients
- `incoming_messages` - Stores messages received from clients
- `outgoing_messages` - Stores messages to be sent to clients

## Setup Instructions

### 1. Twilio Account Setup

1. Create a Twilio account at [twilio.com](https://www.twilio.com/) if you don't have one already
2. Activate the WhatsApp sandbox in your Twilio console
3. Note your Twilio Account SID, Auth Token, and WhatsApp number

### 2. Environment Configuration

Add the following variables to your `.env` file:

```
TWILIO_SID=your_twilio_account_sid
TWILIO_AUTH_TOKEN=your_twilio_auth_token
TWILIO_WHATSAPP_NUMBER=your_twilio_whatsapp_number
```

### 3. Webhook Configuration

In your Twilio WhatsApp sandbox settings, configure the following webhooks:

- **When a message comes in**: `https://your-domain.com/webhooks/twilio/incoming`
- **Status callback URL**: `https://your-domain.com/webhooks/twilio/status`

For local development, you can use a service like [ngrok](https://ngrok.com/) to expose your local server to the internet.

### 4. Running the Scheduler

To ensure outgoing messages are processed regularly, make sure the Laravel scheduler is running:

```bash
php artisan schedule:work
```

In production, you should set up a cron job to run the scheduler every minute:

```
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Usage

### Sending Messages

To send a WhatsApp message:

1. Create a new outgoing message in the admin panel
2. Set the type to 'whatsapp'
3. Enter the recipient's phone number (in international format, e.g., +254712345678)
4. Enter the message content
5. Save the message

The message will be processed and sent by the scheduler.

Alternatively, you can programmatically create and send messages:

```php
use App\Models\OutgoingMessage;
use App\Services\TwilioService;

// Create the message
$message = OutgoingMessage::create([
    'type' => 'whatsapp',
    'phone' => '+254712345678',
    'message' => 'Hello from the application!',
    'status' => 'pending',
]);

// Send immediately
$twilioService = app(TwilioService::class);
$twilioService->sendWhatsAppMessage($message);
```

### Receiving Messages

Incoming messages are automatically received via the Twilio webhook and stored in the database. You can view them in the admin panel under the "Incoming Messages" section.

## Extending the Integration

To customize the message handling logic, modify the `TwilioWebhookController::handleIncomingMessage` method in `app/Http/Controllers/TwilioWebhookController.php`.

## Troubleshooting

- Check the Laravel logs for any errors related to Twilio
- Verify your Twilio credentials in the `.env` file
- Ensure the webhook URLs are correctly configured in your Twilio console
- Make sure the scheduler is running to process outgoing messages
