<?php

use Illuminate\Support\Facades\Log;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        try {
            $this->migrator->add('general.email_template', '');
            $this->migrator->add('general.sms_template', '');
        } catch (\Exception $e) {
            Log::error($e);
        }
    }
};
