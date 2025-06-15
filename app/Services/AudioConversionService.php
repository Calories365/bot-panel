<?php

namespace App\Services;

use App\Services\ChatGPTServices\SpeechToTextService;
use FFMpeg\FFMpeg;
use Illuminate\Support\Facades\Http;
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
            'ffprobe.binaries' => '/usr/bin/ffprobe',
        ]);
        $this->speechToTextService = $speechToTextService;
    }

    /**
     * Main method to process an audio message.
     */
    public function processAudioMessage(Api $telegram, $bot, $message): ?string
    {
        $localPath = null;
        $fullLocalPath = null;
        $convertedPath = null;
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
            Log::error('Error processing audio message: '.$e->getMessage());

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
     */
    private function getDownloadLink(Api $telegram, $bot, $message): ?string
    {
        if (isset($message['voice'])) {
            $audio = $message['voice'];
            $fileId = $audio['file_id'];
            $file = $telegram->getFile(['file_id' => $fileId]);
            $filePath = $file->file_path;
            $token = $bot->token;

            return 'https://api.telegram.org/file/bot'.$token.'/'.$filePath;
        }

        return null;
    }

    /**
     * Downloads a file from a Telegram file URL and stores it on the public disk.
     * Returns [$relativePath, $absolutePath].
     */
    private function downloadAudio(string $downloadLink): array
    {
        $response = Http::timeout(45)
            ->withHeaders(['Accept' => 'audio/ogg'])
            ->get($downloadLink);

        if (! $response->ok()) {
            Log::error("Cannot download voice file: {$downloadLink} (status {$response->status()})");

            return [null, null];
        }

        $fileName = basename($downloadLink);
        $localPath = "audios/{$fileName}";
        Storage::disk('public')->put($localPath, $response->body());

        $fullLocalPath = Storage::disk('public')->path($localPath);

        return [$localPath, $fullLocalPath];
    }

    /**
     * Convert any supported format (oga, ogg, m4a, mp4) to mp3.
     * Returns an array [relativeConvertedPath, absoluteConvertedPath].
     */
    public function convertToMp3(string $localPath, string $sourceFullPath): array
    {
        $extensionToMp3 = function (string $filename) {
            return preg_replace('/\.(oga|ogg|m4a|mp4)$/i', '.mp3', $filename);
        };

        $convertedLocalPath = $extensionToMp3($localPath);
        $convertedFullPath = Storage::disk('public')->path($convertedLocalPath);

        try {
            $audioFile = $this->ffmpeg->open($sourceFullPath);
            $mp3Format = new \FFMpeg\Format\Audio\Mp3;
            $audioFile->save($mp3Format, $convertedFullPath);

            return [$convertedLocalPath, $convertedFullPath];
        } catch (\Exception $e) {
            Log::error('Error converting audio: '.$e->getMessage());

            return [null, null];
        }
    }
}
