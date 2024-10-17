<?php

namespace App\Console\Commands;

use App\Services\APIService\DiaryApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class testApi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-api';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sendService = new DiaryApiService();
        $response = $sendService->sendText('
        Яблоки - 100грамм;
        Творог - 150грамм;
        ');

    }
}
