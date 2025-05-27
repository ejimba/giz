<?php

namespace App\Services\Conversation\Handlers;

use App\Models\Conversation;
use App\Services\Conversation\Contracts\StepHandlerInterface;
use App\Services\Conversation\Step;
use App\Services\EndevStovesService;
use App\Services\TwilioService;
use Illuminate\Support\Facades\Log;
use Exception;

class ConfirmationHandler extends BaseStepHandler implements StepHandlerInterface
{
    protected EndevStovesService $endevStoves;

    public function __construct(TwilioService $twilio, EndevStovesService  $endevStoves)
    {
        parent::__construct($twilio);
        $this->endevStoves = $endevStoves;
    }

    public function step(): string
    {
        return Step::CONFIRMATION;
    }

    public function handle(Conversation $conv, string $message): void
    {
        $meta = $conv->metadata ?? [];
        if (empty($message)) {
            $cart = $meta['cart'] ?? [];
            $total = $meta['order_total'] ?? 0;
            $msg = "Please confirm the sale details:\n\n";
            $msg .= "Customer: {$meta['selected_customer']['name']}\n";
            $msg .= "Staff: {$meta['selected_staff']['name']}\n";
            $msg .= "Date: " . date('d/m/Y', strtotime($meta['sale_date'])) . "\n\n";
            $msg .= "Products:\n";
            foreach ($cart as $i => $item) {
                $name = $item['product']['name'] . ' ' . $item['product']['type'];
                $msg .= ($i + 1) . ". {$name}"
                    . ($item['is_green'] ? ' (Green)' : '')
                    . " - {$item['quantity']} x {$item['unit_price']} = {$item['total']}\n";
            }
            $msg .= "\nTotal Amount: {$total}\n";
            $msg .= "Credit Sale: " . (($meta['on_credit'] ?? false) ? 'Yes' : 'No') . "\n";
            if ($meta['on_credit'] ?? false) {
                $msg .= "Deposit: " . ($meta['deposit'] ?? 0) . "\n";
            }
            $msg .= "\nReply:\n1. Confirm and submit\n2. Cancel\n";
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav($msg)
            );
            $meta['prompt_sent'] = true;
            $conv->update(['metadata' => $meta]);
            return;
        }
        $choice = trim($message);
        if ($choice === '2') {
            $conv->update(['status' => 'canceled']);
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav("Sale canceled.\n")
            );
            $this->endConversation($conv, 'canceled');
            $this->startConversation($conv->client);
            return;
        }
        if ($choice === '1') {
            $cart = $meta['cart'] ?? [];
            if (empty($cart)) {
                $this->twilio->sendWhatsAppMessage(
                    $conv->client->phone,
                    $this->withNav("Cart is empty. Nothing to submit.\n")
                );
                $meta['step'] = Step::PRODUCT_SELECTION;
                $this->transitionTo($conv, $meta['step'], $meta);
                return;
            }
            $orderTotal = $meta['order_total'] ?: 1;
            $allGood = true;
            $lines = [];
            foreach ($cart as $item) {
                $payload = [
                    'product'    => $item['product']['_id'],
                    'customer'   => $meta['selected_customer']['_id'],
                    'staffID'    => $meta['selected_staff']['_id'],
                    'date'       => $meta['sale_date'],
                    'quantity'   => $item['quantity'],
                    'unitPrice'  => $item['unit_price'],
                    'totalPrice' => $item['total'],
                    'green'      => $item['is_green'],
                    'onCredit'   => $meta['on_credit'] ?? false,
                    'deposit'    => 0,
                    'member'     => '',
                ];
                if ($meta['on_credit'] ?? false) {
                    $payload['deposit'] = round(($meta['deposit'] ?? 0) * ($item['total'] / $orderTotal), 2);
                }
                try {
                    $this->endevStoves->createSale($payload);
                    $lines[] = $item['product']['name'] . ' ' . $item['product']['type'] . ': Success';
                } catch (Exception $e) {
                    $allGood = false;
                    Log::error('Sale item submit failed', [
                        'err'  => $e->getMessage(),
                        'conv' => $conv->id,
                        'prod' => $item['product']['name'] ?? '',
                    ]);
                    $lines[] = $item['product']['name'] . ' ' . $item['product']['type']
                            . ': Failed';
                }
            }
            $header = $allGood ? "Sale recorded successfully!\n\n" : "Sale recorded with some errors.\n\n";
            $footer = "\nThank you.\n";
            $this->twilio->sendWhatsAppMessage(
                $conv->client->phone,
                $this->withNav($header . implode("\n", $lines) . $footer)
            );
            $this->endConversation($conv, 'completed');
            $this->startConversation($conv->client);
            return;
        }
        $this->twilio->sendWhatsAppMessage(
            $conv->client->phone,
            $this->withNav("Invalid selection. Reply 1 to confirm or 2 to cancel.\n")
        );
    }
}
