<?php

namespace App\Services\TelegramServices;

use App\Models\Bot;
use App\Services\TelegramServices\Middleware\CheckUserAuthAndLocale;
use Illuminate\Contracts\Container\Container;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

class TelegramHandler
{
    /** @var array<string, callable|BotHandlerStrategy> */
    protected array $strategies;

    /** @var callable(Bot): Api */
    private $apiFactory;

    public function __construct(
        Container $app,
        ?callable $apiFactory = null
    ) {
        $this->strategies = [
            'Approval' => static fn () => $app->make(ApprovalService::class),
            'Default' => static fn () => $app->make(DefaultService::class),
            'Request' => static fn () => $app->make(RequestService::class),
            'Request2' => static fn () => $app->make(Request2Service::class),
            'Calories' => static fn () => $app->make(CaloriesService::class),
            'TikTok' => static fn () => $app->make(TikTokService::class),
        ];

        $this->apiFactory = $apiFactory
            ?: static fn (Bot $bot) => new Api($bot->token);
    }

    public function handle($botName, $request): void
    {
        $bot = Cache::remember("bot:{$botName}", 300, fn () => Bot::with('type')->where('name', $botName)->firstOrFail()
        );
        $botTypeName = $bot->type->name ?? 'unknown';

        if (! $bot->active) {
            return;
        }

        $telegram = ($this->apiFactory)($bot);
        $update = new Update($request->all());

        if (! isset($this->strategies[$botTypeName])) {
            Log::error("Unknown bot type: {$botTypeName}");

            return;
        }

        $strategy = $this->strategies[$botTypeName];
        if ($strategy instanceof \Closure) {
            $strategy = $this->strategies[$botTypeName] = $strategy();
        }

        $passable = $this->runMiddlewares(
            $botTypeName,
            $bot,
            $telegram,
            $update,
            $strategy
        );

        if (! $passable) {
            return;
        }

        $botUser = $passable['botUser'] ?? null;
        $strategy->handle($bot, $telegram, $update, $botUser);
    }

    protected function runMiddlewares($botTypeName, $bot, $telegram, $update, $strategy)
    {
        $middlewares = [
            'Calories' => [
                CheckUserAuthAndLocale::class,
            ],
        ];

        $passable = [
            'botTypeName' => $botTypeName,
            'bot' => $bot,
            'telegram' => $telegram,
            'update' => $update,
            'excludedCommands' => $strategy->getExcludedCommands(),
        ];

        if (! isset($middlewares[$botTypeName])) {
            return $passable;
        }

        return app(Pipeline::class)
            ->send($passable)
            ->through($middlewares[$botTypeName])
            ->then(fn ($p) => $p);
    }
}
