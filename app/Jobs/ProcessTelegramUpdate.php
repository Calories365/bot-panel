<?php

namespace App\Jobs;

use App\Services\TelegramServices\TelegramHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Exceptions\TelegramResponseException;

class ProcessTelegramUpdate implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $botName;

    /** @var array<string,mixed> */
    public array $payload;

    public string $uniqueKey;

    public int $uniqueFor = 600;

    public int $timeout = 150;

    public $backoff = [5, 15, 45, 120];

    public function __construct(string $botName, array $payload, ?int $updateId = null)
    {
        $this->onQueue('telegram');
        $this->botName = $botName;
        $this->payload = $payload;

        $id = $updateId ?? ($payload['update_id'] ?? null);
        if ($id === null) {
            $id = crc32(json_encode($payload, JSON_UNESCAPED_UNICODE));
        }
        $this->uniqueKey = $botName.':'.$id;
    }

    public function uniqueId(): string
    {
        return $this->uniqueKey;
    }

    public function middleware(): array
    {
        return [
            new RateLimited('telegram'),
        ];
    }

    public function handle(TelegramHandler $telegramHandler): void
    {
        try {
            Log::info(2222);
            $req = new Request;
            $req->replace($this->payload);

            $telegramHandler->handle($this->botName, $req);
        } catch (TelegramResponseException $e) {
            $data = $e->getResponseData();
            if (isset($data['parameters']['retry_after'])) {
                $retry = (int) $data['parameters']['retry_after'];
                $this->release($retry + 1);

                return;
            }
            Log::error('Telegram API error: '.$e->getMessage());
            throw $e;
        } catch (\Throwable $e) {
            Log::error('ProcessTelegramUpdate failed', [
                'bot' => $this->botName,
                'update_id' => $this->payload['update_id'] ?? null,
                'type' => $this->payload['message'] ? 'message' :
                    ($this->payload['callback_query'] ? 'callback' :
                        ($this->payload['my_chat_member'] ? 'my_chat_member' : 'other')),
                'err' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
