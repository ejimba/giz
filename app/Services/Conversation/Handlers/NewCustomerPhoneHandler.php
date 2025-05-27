<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Conversation;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\Conversation\Step;
use App\Services\EndevStovesService;
use App\Services\TwilioService;
use Illuminate\Support\Facades\Log;
use Exception;

class NewCustomerPhoneHandler extends BaseStepHandler implements StepHandlerInterface
{
    protected EndevStovesService $endevStoves;

    public function __construct(TwilioService $twilio, EndevStovesService  $endevStoves)
    {
        parent::__construct($twilio);
        $this->endevStoves = $endevStoves;
    }

    public function step(): string
    {
        return Step::NEW_CUSTOMER_PHONE;
    }

    public function handle(Conversation $conv, string $message): void
    {
        $meta = $conv->metadata ?? [];
        if (empty($message)) {
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Enter customer phone number:\n")
            );
            $meta['prompt_sent'] = true;
            $conv->update(['metadata' => $meta]);
            return;
        }
        $phone = trim($message);
        $validPhone = (bool) preg_match('/^[\d\+\-\s]{6,20}$/', $phone);
        if (!$validPhone || $phone == '') {
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Phone number is invalid. Please re-enter:\n")
            );
            return;
        }
        try {
            $customer = $this->endevStoves->createCustomer([
                'name' => $meta['new_customer_name'] ?? 'Unnamed',
                'phoneNumber' => $phone,
                'location' => '',
                'type' => '',
                'IDNumber' => '',
                'contactPerson' => '',
                'member' => '',
            ]);
            $meta['selected_customer'] = $customer;
            $meta['creating_customer'] = false;
            $meta['step'] = Step::DATE_SELECTION;
            unset($meta['prompt_sent']);
            $conv->update(['metadata' => $meta]);
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Customer created successfully.")
            );
            $this->transitionTo($conv, $meta['step'], $meta);
            return;
        } catch (Exception $e) {
            Log::error('Customer creation failed', [
                'err' => $e->getMessage(),
                'conv' => $conv->id,
            ]);
            $meta['step'] = Step::HANDLE_CUSTOMER_CREATION_ERROR;
            unset($meta['prompt_sent']);
            $this->transitionTo($conv, $meta['step'], $meta);
            return;
        }
    }
}
