<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Conversation;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\Conversation\Step;
use App\Services\EndevStovesService;
use App\Services\TwilioService;

class CustomerHandler extends BaseStepHandler implements StepHandlerInterface
{
    protected EndevStovesService $endevStoves;

    public function __construct(TwilioService $twilio, EndevStovesService  $endevStoves)
    {
        parent::__construct($twilio);
        $this->endevStoves = $endevStoves;
    }

    public function step(): string
    {
        return Step::CUSTOMER_SELECTION;
    }

    public function handle(Conversation $conv, string $message): void
    {
        $meta = $conv->metadata ?? [];
        if (empty($message)) {
            $customers= $this->endevStoves->fetchCustomers();
            $meta['customers'] = $customers;
            $meta['prompt_sent'] = true;
            $conv->update(['metadata' => $meta]);
            $list = "Select a customer:\n";
            foreach ($customers as $i => $cust) {
                $list .= ($i + 1) . '. ' . $cust['name'] . "\n";
            }
            $list .= (count($customers) + 1) . ". Create New Customer\n";
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav($list)
            );
            return;
        }
        $sel = (int) trim($message);
        $customers = $meta['customers'] ?? [];
        if ($sel === count($customers) + 1) {
            $meta['step'] = Step::NEW_CUSTOMER_NAME;
            unset($meta['prompt_sent']);
            $conv->update(['metadata' => $meta]);
            $this->transitionTo($conv, $meta['step'], $meta);
            return;
        }
        if ($sel > 0 && $sel <= count($customers)) {
            $meta['selected_customer'] = $customers[$sel - 1];
            $meta['step'] = Step::DATE_SELECTION;
            unset($meta['prompt_sent']);
            $this->transitionTo($conv, $meta['step'], $meta);
            return;
        }
        $this->twilio->sendWhatsAppMessage(
            $conv->client->phone,
            $this->withNav("Invalid selection. Please choose a number from the list.")
        );
    }
}
