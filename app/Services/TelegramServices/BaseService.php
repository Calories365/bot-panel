<?php

namespace App\Services\TelegramServices;

use App\Models\Bot;
use App\Models\BotUser;
use App\Services\TelegramServices\BaseHandlers\MessageHandlers\AudioMessageHandler;
use App\Services\TelegramServices\BaseHandlers\MessageHandlers\TextMessageHandler;
use App\Services\TelegramServices\BaseHandlers\TextMessageHandlers\StartMessageHandler;
use App\Services\TelegramServices\BaseHandlers\UpdateHandlers\CallbackQueryHandler;
use App\Services\TelegramServices\BaseHandlers\UpdateHandlers\MessageUpdateHandler;
use App\Services\TelegramServices\BaseHandlers\UpdateHandlers\MyChatMemberUpdateHandler;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

/**
 * Class BaseService
 *
 * This service implements BotHandlerStrategy to handle Telegram bot updates
 * and distribute them to the appropriate handlers.
 */
class BaseService implements BotHandlerStrategy
{
    protected array $updateHandlers;

    /**
     * BaseService constructor.
     * Initializes the update handlers by calling the getUpdateHandlers method.
     */
    public function __construct()
    {
        $this->updateHandlers = $this->getUpdateHandlers();
    }

    /**
     * BaseService getUpdateHandlers.
     * Collects and returns base UpdateHandlers and for each UpdateHandler passes base subhandlers
     */
    protected function getUpdateHandlers(): array
    {
        return [
            'message' => function () {
                return app(MessageUpdateHandler::class, [
                    'messageHandlers' => $this->getMessageHandlers(),
                ]);
            },
            'my_chat_member' => function () {
                return app(MyChatMemberUpdateHandler::class);
            },
            'callback_query' => function () {
                return app(CallbackQueryHandler::class, [
                    'callbackQueryHandlers' => $this->getCallbackQueryHandlers(),
                ]);
            },
        ];
    }

    /**
     * BaseService getMessageHandlers.
     * collects and returns all basic MessageHandlers
     */
    protected function getMessageHandlers(): array
    {
        return [
            'text' => function () {
                return app(TextMessageHandler::class, [
                    'textMessageHandlers' => $this->getTextMessageHandlers(),
                ]);
            },
            'voice' => function () {
                return app(AudioMessageHandler::class);
            },
        ];
    }

    /**
     * BaseService getTextMessageHandlers.
     * collects and returns all basic TextMessageHandlers
     */
    protected function getTextMessageHandlers(): array
    {
        return [
            '/start' => static fn () => app(StartMessageHandler::class),
            '/default' => static fn () => app(StartMessageHandler::class),
        ];
    }

    /**
     * BaseService getCallbackQueryHandlers.
     * collects and returns all basic CallbackQueryHandlers
     */
    protected function getCallbackQueryHandlers(): array
    {
        return [];
    }

    /**
     * Get list of commands that should be excluded from middleware processing
     * Default implementation returns empty array
     */
    public function getExcludedCommands(): array
    {
        return [];
    }

    /**
     * BaseService handle.
     * starts the required Handler for the event
     */
    public function handle(Bot $bot, Api $telegram, Update $update, ?BotUser $botUser): void
    {
        $updateType = $update->detectType();

        if (isset($this->updateHandlers[$updateType])) {
            $handler = $this->updateHandlers[$updateType];

            if ($handler instanceof \Closure) {
                $handler = $this->updateHandlers[$updateType] = $handler();
            }

            $handler->handle($bot, $telegram, $update, $botUser);
        } else {
            Log::info('Unhandled update type: '.$updateType);
        }
    }
}
