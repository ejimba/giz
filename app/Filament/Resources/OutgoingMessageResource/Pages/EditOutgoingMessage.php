<?php

namespace App\Filament\Resources\OutgoingMessageResource\Pages;

use App\Filament\Resources\OutgoingMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOutgoingMessage extends EditRecord
{
    protected static string $resource = OutgoingMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
