<?php

namespace App\Filament\Pages;

use App\Models\EchisOrganisationUnit;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Illuminate\Support\Collection;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Section::make()->schema([
                DatePicker::make('start_date')->label('Start Date'),
                DatePicker::make('end_date')->label('End Date'),
            ])->columns(2),
        ]);
    }
}
