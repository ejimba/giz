<?php

namespace App\Filament\Resources\OutgoingMessageResource\Pages;

use App\Filament\Resources\OutgoingMessageResource;
use App\Jobs\ProcessOutgoingMessages;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListOutgoingMessages extends ListRecords
{
    protected static string $resource = OutgoingMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('Process Outgoing Messages')
                ->label('Process Outgoing Messages')
                ->requiresConfirmation()
                ->action(function () {
                    ProcessOutgoingMessages::dispatch();
                    Notification::make()->title('Outgoing messages queued successfully')->success()->send();
                }),
        ];
    }
}
