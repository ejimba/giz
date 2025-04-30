<?php

namespace App\Filament\Widgets;

use App\Models\Client;
use App\Models\User;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    use InteractsWithPageFilters;
    
    protected function getStats(): array
    {
        $client = Client::query();
        if ($this->filters['start_date']) {
            $client = $client->whereDate('created_at', '>=', $this->filters['start_date']);
        }

        if ($this->filters['end_date']) {
            $client = $client->whereDate('created_at', '<=', $this->filters['end_date']);
        }

        return [
            Stat::make(label: 'Clients', value: $client->count()),
            Stat::make('System Users', User::query()->count()),
        ];
    }
}
