<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessOutgoingMessages extends Command
{
    protected $signature = 'app:process-outgoing-messages';

    protected $description = 'Process Outgoing Messages';

    public function handle()
    {
        dispatch_sync(new \App\Jobs\ProcessOutgoingMessages());
    }
}
