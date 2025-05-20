<?php

namespace App\Filament\Resources\ClientResource\Widgets;

use App\Models\Client;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Carbon;

class ClientsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Clients';

    protected function getData(): array
    {
        $start_date = $this->filters['start_date'] ?? now()->startOfYear();
        $end_date = $this->filters['end_date'] ?? now();
        $query = Client::query();

        $data = Trend::query($query)
            ->between(start: Carbon::parse($start_date), end: Carbon::parse($end_date))
            ->{$this->getPeriod($start_date, $end_date)}()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Number of Clients',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate)
                ]
            ],
            'labels' => $data->map(fn (TrendValue $value) => $value->date),
        ];
    }

    protected function getPeriod($start_date, $end_date): string
    {
        $daysDifference = Carbon::parse($start_date)->diffInDays(Carbon::parse($end_date));
        $monthsDifference = Carbon::parse($start_date)->diffInMonths(Carbon::parse($end_date));

        return $monthsDifference > 12 ? 'perYear' : ($daysDifference > 30 ? 'perMonth' : 'perDay');
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
