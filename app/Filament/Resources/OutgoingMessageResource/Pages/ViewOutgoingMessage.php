<?php

namespace App\Filament\Resources\OutgoingMessageResource\Pages;

use App\Filament\Resources\OutgoingMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOutgoingMessage extends ViewRecord
{
    protected static string $resource = OutgoingMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
