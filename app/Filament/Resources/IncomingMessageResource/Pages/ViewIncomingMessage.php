<?php

namespace App\Filament\Resources\IncomingMessageResource\Pages;

use App\Filament\Resources\IncomingMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewIncomingMessage extends ViewRecord
{
    protected static string $resource = IncomingMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
