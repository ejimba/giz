<?php

namespace App\Filament\Resources\IncomingMessageResource\Pages;

use App\Filament\Resources\IncomingMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditIncomingMessage extends EditRecord
{
    protected static string $resource = IncomingMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
