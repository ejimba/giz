<?php

namespace App\Filament\Resources\IncomingMessageResource\Pages;

use App\Filament\Resources\IncomingMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListIncomingMessages extends ListRecords
{
    protected static string $resource = IncomingMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
