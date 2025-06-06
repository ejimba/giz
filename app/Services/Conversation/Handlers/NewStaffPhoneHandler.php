<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Conversation;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\Conversation\NavigationService;
use App\Services\Conversation\Step;
use App\Services\EndevStovesService;
use App\Services\TwilioService;
use Exception;
use Illuminate\Support\Facades\Log;

class NewStaffPhoneHandler extends BaseStepHandler implements StepHandlerInterface
{
    protected EndevStovesService $endevStoves;
    protected NavigationService $nav;

    public function __construct(TwilioService $twilio, EndevStovesService $endevStoves, NavigationService $nav)
    {
        parent::__construct($twilio);
        $this->endevStoves = $endevStoves;
        $this->nav = $nav;
    }

    public function step(): string
    {
        return Step::NEW_STAFF_PHONE;
    }

    public function handle(Conversation $conv, string $message): void
    {
        $meta = $conv->metadata ?? [];
        
        if (empty($message)) {
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Please enter the staff member's phone number:")
            );
            return;
        }
        
        // Store the staff phone number
        $meta['new_staff_phone'] = trim($message);
        $conv->update(['metadata' => $meta]);
        
        try {
            $staffData = [
                'name' => $meta['new_staff_name'],
                'type' => '',
                'gender' => '',
                'phoneNumber' => $meta['new_staff_phone'],
                'IDNumber' => '',
                'KRAPin' => '',
                'hoursWorked' => '8',
                'daysWorked' => '5'
            ];
            $this->endevStoves->createStaff($staffData);
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("✅ Staff member {$staffData['name']} has been successfully added.")
            );
            $this->nav->resetToMain($conv);
        } catch (Exception $e) {
            Log::error('Failed to create staff member', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'conv_id' => $conv->id,
                'staff_data' => $staffData ?? null,
            ]);
            $meta['staff_error'] = $e->getMessage();
            $meta['step'] = Step::HANDLE_STAFF_CREATION_ERROR;
            $conv->update(['metadata' => $meta]);
            $this->transitionTo($conv, Step::HANDLE_STAFF_CREATION_ERROR, $meta);
        }
    }
}
