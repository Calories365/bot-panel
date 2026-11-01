<?php

namespace App\Console\Commands;

use App\Services\TelegramServices\OnecHelperHandlers\OnecJobBoard;
use Illuminate\Console\Command;

class SuperviseOnecJobs extends Command
{
    protected $signature = 'onec-helper:supervise';

    protected $description = 'Return the 1C helper jobs of a silent worker to the queue and warn the owner of a queue nobody takes';

    public function handle(OnecJobBoard $board): int
    {
        $board->supervise();

        return self::SUCCESS;
    }
}
