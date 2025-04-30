<?php


use App\Jobs\ProcessOutgoingMessages;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ProcessOutgoingMessages)->everyTwoMinutes()->withoutOverlapping();

Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run --only-db')->daily()->at('01:30');
