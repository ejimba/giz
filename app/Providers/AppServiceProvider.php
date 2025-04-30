<?php

namespace App\Providers;

use App\Models\AppReferral;
use App\Models\OutgoingMessage;
use App\Models\User;
use App\Observers\AppReferralObserver;
use App\Observers\OutgoingMessageObserver;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('viewPulse', function (User $user) {
            return true;
        });
        User::observe(UserObserver::class);
        OutgoingMessage::observe(OutgoingMessageObserver::class);
    }
}
