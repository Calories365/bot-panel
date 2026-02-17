<?php

namespace App\Services\Benchmark;

use App\Services\AudioConversionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

/**
 * Replaces AudioConversionService during benchmarks.
 * Reads audio from a local file (path stored in Redis) instead of downloading from Telegram.
 * FFmpeg conversion and SpeechToTextService calls remain real.
 */
class BenchmarkAudioConversionService extends AudioConversionService
{
    public function processAudioMessage($telegram, $bot, $message): ?string
    {
        $localPath = null;
        $convertedLocal = null;

        try {
            // Resolve request_id from the voice.file_id in the message payload.
            $fileId = $message['voice']['file_id'] ?? '';
            $requestId = Redis::get('benchmark:fileid_to_request:'.$fileId);

            if (! $requestId) {
                Log::error('BenchmarkAudioConversionService: no request_id for file_id='.$fileId);

                return null;
            }

            // Set context so SpeechToTextService can record timing.
            BenchmarkContext::$currentRequestId = $requestId;

            // Read the audio file path from Redis.
            $audioSourcePath = Redis::get('benchmark:audio:'.$requestId);
            if (! $audioSourcePath || ! file_exists($audioSourcePath)) {
                Log::error('BenchmarkAudioConversionService: audio file not found', [
                    'request_id' => $requestId,
                    'path' => $audioSourcePath,
                ]);

                return null;
            }

            // Copy to public disk with unique name to avoid race conditions in concurrent mode.
            $fileName = 'benchmark_'.$requestId.'_'.basename($audioSourcePath);
            $localPath = "audios/{$fileName}";
            Storage::disk('public')->put($localPath, file_get_contents($audioSourcePath));
            $fullLocalPath = Storage::disk('public')->path($localPath);

            Log::info('BenchmarkAudioConversionService: processing audio', [
                'request_id' => $requestId,
                'source' => $audioSourcePath,
                'local' => $fullLocalPath,
            ]);

            // Real FFmpeg conversion.
            [$convertedLocal, $convertedPath] = $this->convertToMp3($localPath, $fullLocalPath);

            if ($convertedPath) {
                return $this->speechToTextService->convertSpeechToText($convertedPath);
            }

            Log::error('BenchmarkAudioConversionService: FFmpeg conversion failed');

            return null;
        } catch (\Exception $e) {
            Log::error('BenchmarkAudioConversionService error: '.$e->getMessage());

            return null;
        } finally {
            if ($localPath && Storage::disk('public')->exists($localPath)) {
                Storage::disk('public')->delete($localPath);
            }
            if ($convertedLocal && Storage::disk('public')->exists($convertedLocal)) {
                Storage::disk('public')->delete($convertedLocal);
            }
            BenchmarkContext::reset();
        }
    }
}
