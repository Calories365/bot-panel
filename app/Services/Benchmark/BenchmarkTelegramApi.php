<?php

namespace App\Services\Benchmark;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\File;
use Telegram\Bot\Objects\Message;

/**
 * Extends the real Telegram Api so it passes type hints (BaseService::handle expects Api).
 * Intercepts sendMessage calls and pushes results to Redis instead of Telegram.
 */
class BenchmarkTelegramApi extends Api
{
    private string $requestId;

    private array $sentMessages = [];

    public function __construct(string $requestId)
    {
        // Pass a dummy token — we never actually call Telegram.
        parent::__construct('benchmark-dummy-token');
        $this->requestId = $requestId;
    }

    public function sendMessage(array $params): Message
    {
        $this->sentMessages[] = $params;

        Log::debug('BenchmarkTelegramApi::sendMessage', [
            'request_id' => $this->requestId,
            'chat_id' => $params['chat_id'] ?? null,
            'text_length' => strlen($params['text'] ?? ''),
        ]);

        return new Message([
            'message_id' => random_int(100000, 999999),
            'chat' => ['id' => $params['chat_id'] ?? 0, 'type' => 'private'],
            'date' => time(),
            'text' => $params['text'] ?? '',
        ]);
    }

    public function getFile(array $params): File
    {
        return new File(['file_id' => $params['file_id'] ?? '', 'file_path' => 'benchmark/fake_audio.oga']);
    }

    /**
     * Flush all collected messages to Redis so the benchmark command can read them.
     */
    public function flushResults(): void
    {
        $payload = json_encode([
            'request_id' => $this->requestId,
            'messages' => $this->sentMessages,
            'messages_count' => count($this->sentMessages),
            'completed_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE);

        Redis::rpush('benchmark:results:'.$this->requestId, $payload);
        Redis::expire('benchmark:results:'.$this->requestId, 300);
    }

    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }
}
