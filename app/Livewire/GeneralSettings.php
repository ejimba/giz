<?php

namespace App\Livewire;

use App\Settings\GeneralSettings as SettingsGeneralSettings;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Spatie\LaravelSettings\Migrations\SettingsMigrator;

class GeneralSettings extends Component implements HasForms, HasActions
{
    use InteractsWithActions, InteractsWithForms;

    public ?array $data = [];
    
    public function mount(): void
    {
        $settings = app(SettingsGeneralSettings::class);
        try {
            $this->form->fill([
                'email_template' => $settings->email_template ?? '',
                'sms_template' => $settings->sms_template ?? '',
            ]);
        } catch (\Exception $e) {
            Log::error($e);
            Notification::make()->title('An error occurred while fetching settings')->danger()->send();
        }
    }
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Textarea::make('email_template')->label('Email Template')->columnSpan('full')->rows(20)->required(),
                Textarea::make('sms_template')->label('SMS Template')->columnSpan('full')->rows(5)->required(),
            ])
            ->statePath('data');
    }
    
    public function update(): void
    {
        $formData = $this->form->getState();
        $settings = app(SettingsGeneralSettings::class);
        try {
            $settings->email_template = $formData['email_template'];
            $settings->sms_template = $formData['sms_template'];
            $settings->save();
            Notification::make()->title('Settings updated successfully')->success()->send();
        } catch (\Exception $e) {
            Log::error($e);
            Notification::make()->title('An error occurred while saving settings')->danger()->send();
        }
    }

    public function render(): View
    {
        return view('livewire.general-settings');
    }
}
