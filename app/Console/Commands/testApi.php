<?php

namespace App\Console\Commands;

use App\Services\AudioConversionService;
use App\Services\ChatGPTServices\SpeechToTextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class testApi extends Command
{
    protected $signature = 'test';

    protected $description = 'Command description';

    public function handle()
    {
        Log::info('Testing audio conversion');
        $fullLocalPath = '/var/www/bot-panel/storage/app/public/audios/file_110.oga';
        $localPath = 'audios/file_110.oga';

        $speechToTextService = new SpeechToTextService;
        $audioConversionService = new AudioConversionService($speechToTextService);
        [$convertedLocalPath, $convertedFullPath] = $audioConversionService->convertToMp3($localPath, $fullLocalPath);

        try {
            if ($convertedFullPath) {
                $this->info('Файл успешно конвертирован в: '.$convertedFullPath);
            } else {
                $this->error('Ошибка конвертации аудио');
            }
        } catch (\Exception $e) {
            $this->error('Ошибка конвертации аудио: '.$e->getMessage());
        }
    }
}
