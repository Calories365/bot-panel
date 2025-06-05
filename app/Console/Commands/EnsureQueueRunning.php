<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EnsureQueueRunning extends Command
{
    protected $signature = 'queue:ensure-running';

    protected $description = 'Ensure that the queue worker is running';

    public function handle()
    {
        $pidPath = storage_path('queue-worker.pid');

        if (file_exists($pidPath)) {
            $pid = file_get_contents($pidPath);
            if ($pid !== false && is_numeric($pid) && posix_getpgid((int) $pid)) {
                $this->info('Queue worker is running');

                return;
            }
        }

        $command = 'php '.base_path('artisan').' queue:work --daemon --tries=3 --sleep=3 > /dev/null 2>&1 & echo $!';
        $pid = exec($command);
        file_put_contents($pidPath, $pid);

        $this->info('Queue worker has been started');
    }
}
