<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Conversation;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\Conversation\Step;
use App\Services\EndevStovesService;
use App\Services\TwilioService;

class StaffHandler extends BaseStepHandler implements StepHandlerInterface
{
    protected EndevStovesService $endevStoves;

    public function __construct(TwilioService $twilio, EndevStovesService  $endevStoves)
    {
        parent::__construct($twilio);
        $this->endevStoves = $endevStoves;
    }

    public function step(): string
    {
        return Step::STAFF_SELECTION;
    }

    public function handle(Conversation $conv, string $message): void
    {
        $meta = $conv->metadata ?? [];
        if (empty($message)) {
            $staff = $this->endevStoves->fetchStaff();
            $meta['staff'] = $staff;
            $meta['prompt_sent'] = true;
            $conv->update(['metadata' => $meta]);
            $list = "Select a staff member:\n";
            foreach ($staff as $i => $member) {
                $list .= ($i + 1) . '. ' . $member['name'] . "\n";
            }
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav($list)
            );
            return;
        }
        $sel = (int) trim($message);
        $staff = $meta['staff'] ?? [];
        if ($sel < 1 || $sel > count($staff)) {
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav('Invalid selection. Please choose a number from the list.')
            );
            return;
        }
        $selectedStaff = $staff[$sel - 1];
        $meta['selected_staff'] = $selectedStaff;
        $meta['staff'] = $staff;
        $meta['step'] = Step::CUSTOMER_SELECTION;
        unset($meta['prompt_sent']);
        $this->transitionTo($conv, $meta['step'], $meta);
    }
}
