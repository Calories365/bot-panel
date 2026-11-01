<?php

namespace App\Console\Commands;

use App\Services\TelegramServices\OnecHelperHandlers\OnecJobBoard;
use Illuminate\Console\Command;

class DeleteOldOnecFiles extends Command
{
    protected $signature = 'onec-helper:delete-old-files';

    protected $description = 'Delete from the disk the files of the 1C helper questions older than ONEC_HELPER_RETENTION_DAYS';

    public function handle(OnecJobBoard $board): int
    {
        $this->info('Questions cleaned: '.$board->deleteOldFiles());

        return self::SUCCESS;
    }
}
