<?php

namespace App\Filament\Resources\ConversationResource\Widgets;

use Filament\Widgets\ChartWidget;

class ConversationStats extends ChartWidget
{
    protected static ?string $heading = 'Chart';

    protected function getData(): array
    {
        return [
            //
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
