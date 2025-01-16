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
            'ffmpeg.binaries'  => '/usr/bin/ffmpeg',
            'ffprobe.binaries' => '/usr/bin/ffprobe'
        ]);
        $this->speechToTextService = $speechToTextService;
    }

    /**
     * Main method to process an audio message.
     *
     * @param Api $telegram
     * @param $bot
     * @param $message
     * @return string|null
     */
    public function processAudioMessage(Api $telegram, $bot, $message): ?string
    {
        $localPath      = null;
        $fullLocalPath  = null;
        $convertedPath  = null;
        $convertedLocal = null;

        try {
            $downloadLink = $this->getDownloadLink($telegram, $bot, $message);

            [$localPath, $fullLocalPath] = $this->downloadAudio($downloadLink);

            [$convertedLocal, $convertedPath] = $this->convertToMp3($localPath, $fullLocalPath);

            if ($convertedPath) {
                $text = $this->speechToTextService->convertSpeechToText($convertedPath);
                return $text;
            } else {
                Log::error('Audio conversion failed.');
                return null;
            }
        } catch (\Exception $e) {
            Log::error("Error processing audio message: " . $e->getMessage());
            return null;
        } finally {
            if ($localPath && Storage::disk('public')->exists($localPath)) {
                Storage::disk('public')->delete($localPath);
            }
            if ($convertedLocal && Storage::disk('public')->exists($convertedLocal)) {
                Storage::disk('public')->delete($convertedLocal);
            }
        }
    }

    /**
     * Retrieve the download link from Telegram.
     *
     * @param Api $telegram
     * @param $bot
     * @param $message
     * @return string|null
     */
    private function getDownloadLink(Api $telegram, $bot, $message): ?string
    {
        if (isset($message['voice'])) {
            $audio   = $message['voice'];
            $fileId  = $audio['file_id'];
            $file    = $telegram->getFile(['file_id' => $fileId]);
            $filePath = $file->getFilePath();
            $token    = $bot->token;

            return "https://api.telegram.org/file/bot" . $token . "/" . $filePath;
        }
        return null;
    }

    /**
     * Download the audio file to local storage (public disk).
     * Returns an array [relativePath, absolutePath].
     *
     * @param string $downloadLink
     * @return array
     */
    private function downloadAudio(string $downloadLink): array
    {
        $contents = file_get_contents($downloadLink);

        $fileName  = basename($downloadLink);
        $localPath = 'audios/' . $fileName;
        Storage::disk('public')->put($localPath, $contents);
        $fullLocalPath = Storage::disk('public')->path($localPath);

        return [$localPath, $fullLocalPath];
    }

    /**
     * Convert any supported format (oga, ogg, m4a, mp4) to mp3.
     * Returns an array [relativeConvertedPath, absoluteConvertedPath].
     *
     * @param string $localPath
     * @param string $sourceFullPath
     * @return array
     */
    public function convertToMp3(string $localPath, string $sourceFullPath): array
    {
        $extensionToMp3 = function (string $filename) {
            return preg_replace('/\.(oga|ogg|m4a|mp4)$/i', '.mp3', $filename);
        };

        $convertedLocalPath = $extensionToMp3($localPath);
        $convertedFullPath  = Storage::disk('public')->path($convertedLocalPath);

        try {
            $audioFile = $this->ffmpeg->open($sourceFullPath);
            $mp3Format = new \FFMpeg\Format\Audio\Mp3();
            $audioFile->save($mp3Format, $convertedFullPath);

            return [$convertedLocalPath, $convertedFullPath];
        } catch (\Exception $e) {
            Log::error("Error converting audio: " . $e->getMessage());
            return [null, null];
        }
    }
}
