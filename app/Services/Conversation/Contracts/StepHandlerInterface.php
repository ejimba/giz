<?php

namespace App\Services\Conversation\Contracts;

use App\Models\Conversation;

interface StepHandlerInterface
{
    public function step(): string;

    public function handle(Conversation $conversation, string $message): void;
}
