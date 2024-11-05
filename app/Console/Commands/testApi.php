<?php

namespace App\Console\Commands;

use App\Services\AudioConversionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class testApi extends Command
{
    protected $signature = 'test';
    protected $description = 'Command description';

    public function handle()
    {
        Log::info(111);
        $fullLocalPath = '/var/www/bot-panel/storage/app/public/audios/file_110.oga';
        $convertedPath = str_replace('.oga', '.mp3', $fullLocalPath);
        $audioConversionService = new AudioConversionService();
        $convertedPath = $audioConversionService->convertToMp3($fullLocalPath);
        try {
            $this->info("Файл успешно конвертирован в: " . $convertedPath);
        } catch (\Exception $e) {
            $this->error("Ошибка конвертации аудио: " . $e->getMessage());
        }
    }
}
