<?php

namespace App\Filament\Resources\OutgoingMessageResource\Widgets;

use App\Models\OutgoingMessage;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Carbon;

class OutgoingMessagesChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Outgoing Messages';

    protected function getData(): array
    {
        $start_date = $this->filters['start_date'] ?? now()->startOfYear();
        $end_date = $this->filters['end_date'] ?? now();
        $query = OutgoingMessage::query();
        $data = Trend::query($query)
            ->between(start: Carbon::parse($start_date), end: Carbon::parse($end_date))
            ->dateColumn('processed_at')
            ->{$this->getPeriod($start_date, $end_date)}()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Number of Outgoing Messages',
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