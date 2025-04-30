<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessOutgoingMessages extends Command
{
    protected $signature = 'app:process-outgoing-messages';

    protected $description = 'Process Outgoing Messages';

    public function handle()
    {
        $this->info('Processing outgoing messages...');
        dispatch_sync(new \App\Jobs\ProcessOutgoingMessages());
        $this->info('Outgoing messages processed successfully!');
    }
}
