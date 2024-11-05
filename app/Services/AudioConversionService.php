<?php

namespace App\Services;

use App\Services\ChatGPTServices\SpeechToTextService;
use FFMpeg\FFMpeg;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\Api;

class AudioConversionService
{
    protected FFMpeg $ffmpeg;
    protected SpeechToTextService $speechToTextService;

    public function __construct(SpeechToTextService $speechToTextService)
    {
        $this->ffmpeg = FFMpeg::create([
            'ffmpeg.binaries' => '/usr/bin/ffmpeg',
            'ffprobe.binaries' => '/usr/bin/ffprobe'
        ]);
        $this->speechToTextService = $speechToTextService;
    }

    /**
     * Основной метод для обработки аудио сообщения
     *
     * @param Api $telegram
     * @param $bot
     * @param $message
     * @return string|null
     */
    public function processAudioMessage(Api $telegram, $bot, $message): ?string
    {
        try {
            $downloadLink = $this->getDownloadLink($telegram, $bot, $message);
            $localFilePath = $this->downloadAudio($downloadLink);

            $convertedPath = $this->convertToMp3($localFilePath);

            if ($convertedPath) {
                Log::info('Audio converted successfully: ' . $convertedPath);

                // Преобразование аудио в текст
                $text = $this->speechToTextService->convertSpeechToText($convertedPath);

                return $text;
            } else {
                Log::error('Audio conversion failed.');
                return null;
            }
        } catch (\Exception $e) {
            Log::error("Error processing audio message: " . $e->getMessage());
            return null;
        }
    }

    private function getDownloadLink(Api $telegram, $bot, $message): ?string
    {
        if (isset($message['voice'])) {
            $audio = $message['voice'];
            $fileId = $audio['file_id'];
            $file = $telegram->getFile(['file_id' => $fileId]);
            $filePath = $file->getFilePath();
            $token = $bot->token;
            return "https://api.telegram.org/file/bot" . $token . "/" . $filePath;
        }
        return null;
    }

    private function downloadAudio(string $downloadLink): string
    {
        $contents = file_get_contents($downloadLink);
        $localPath = 'audios/' . basename($downloadLink);
        Storage::disk('public')->put($localPath, $contents);
        $fullLocalPath = Storage::disk('public')->path($localPath);

        return $fullLocalPath;
    }

    public function convertToMp3(string $sourcePath): ?string
    {
        $convertedPath = str_replace(['.oga', '.ogg', '.m4a', '.mp4'], '.mp3', $sourcePath);

        try {
            $audioFile = $this->ffmpeg->open($sourcePath);
            $mp3Format = new \FFMpeg\Format\Audio\Mp3();
            $audioFile->save($mp3Format, $convertedPath);

            return $convertedPath;
        } catch (\Exception $e) {
            Log::error("Ошибка конвертации аудио: " . $e->getMessage());
            return null;
        }
    }
}
