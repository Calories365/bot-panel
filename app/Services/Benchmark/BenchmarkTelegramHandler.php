<?php

namespace App\Services\Benchmark;

use App\Models\Bot;
use App\Services\TelegramServices\TelegramHandler;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Telegram\Bot\Api;

/**
 * Wraps TelegramHandler to inject BenchmarkTelegramApi for benchmark requests,
 * while falling back to real Telegram API for normal user requests.
 *
 * Benchmark requests are identified by having a voice file_id or callback_query.id
 * that maps to a request_id in Redis (set by BenchmarkCommand / BenchmarkGenerateCommand).
 */
class BenchmarkTelegramHandler extends TelegramHandler
{
    private ?BenchmarkTelegramApi $currentBenchmarkApi = null;

    private ?string $pendingRequestId = null;

    public function __construct(Container $app)
    {
        parent::__construct($app, function (Bot $bot) {
            // Not a benchmark request — use real Telegram API.
            if (! $this->pendingRequestId) {
                return new Api($bot->token);
            }

            Log::info('BenchmarkTelegramHandler: benchmark request', [
                'bot' => $bot->name,
                'request_id' => $this->pendingRequestId,
            ]);

            $this->currentBenchmarkApi = new BenchmarkTelegramApi($this->pendingRequestId);

            return $this->currentBenchmarkApi;
        });
    }

    public function handle($botName, $request): void
    {
        // Extract file_id or callback_query.id from the webhook payload to resolve request_id.
        $this->pendingRequestId = $this->resolveRequestIdFromPayload($request);

        // Set BenchmarkContext so SpeechToTextService can record timing.
        // For voice flow, BenchmarkAudioConversionService also sets this (same value).
        // For callback_query flow, this is the only place it gets set.
        if ($this->pendingRequestId) {
            BenchmarkContext::$currentRequestId = $this->pendingRequestId;
        }

        try {
            parent::handle($botName, $request);
        } finally {
            if ($this->currentBenchmarkApi) {
                $this->currentBenchmarkApi->flushResults();
                $this->currentBenchmarkApi = null;
            }
            $this->pendingRequestId = null;
            BenchmarkContext::reset();
        }
    }

    private function resolveRequestIdFromPayload($request): ?string
    {
        $data = method_exists($request, 'all') ? $request->all() : (array) $request;

        // Voice/audio benchmark requests.
        $fileId = $data['message']['voice']['file_id']
            ?? $data['message']['audio']['file_id']
            ?? null;

        if ($fileId) {
            return Redis::get('benchmark:fileid_to_request:'.$fileId);
        }

        // Callback query benchmark requests (generate KBJU benchmark).
        $callbackId = $data['callback_query']['id'] ?? null;
        if ($callbackId) {
            return Redis::get('benchmark:callbackid_to_request:'.$callbackId);
        }

        return null;
    }
}
