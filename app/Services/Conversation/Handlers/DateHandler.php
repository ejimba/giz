<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Conversation;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\Conversation\Step;
use App\Services\TwilioService;
use DateTime;

class DateHandler extends BaseStepHandler implements StepHandlerInterface
{
    public function __construct(TwilioService $twilio)
    {
        parent::__construct($twilio);
    }

    public function step(): string
    {
        return Step::DATE_SELECTION;
    }

    public function handle(Conversation $conv, string $message): void
    {
        $meta = $conv->metadata ?? [];
        if (empty($message)) {
            $today = date('d/m/Y');
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Enter sale date (DD/MM/YYYY) or reply 1 for today ({$today}):\n")
            );
            return;
        }
        $msg = trim($message);
        $dateY = null;
        if ($msg === '1') {
            $dateY = date('Y-m-d');
        } else {
            $dt = DateTime::createFromFormat('d/m/Y', $msg);
            if ($dt !== false) $dateY = $dt->format('Y-m-d');
        }
        if (!$dateY) {
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Invalid date. Use DD/MM/YYYY or 1 for today.\n")
            );
            return;
        }
        $meta['sale_date'] = $dateY;
        $meta['step'] = Step::PRODUCT_SELECTION;
        unset($meta['prompt_sent']);
        $this->transitionTo($conv, $meta['step'], $meta);
    }
}
