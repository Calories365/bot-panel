<?php

namespace App\Services\TelegramServices;

use App\Models\Bot;
use App\Models\BotUser;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\CallbackQueryHandler;
use App\Services\TelegramServices\OnecHelperHandlers\ButtonCallbackQueryHandler;
use App\Services\TelegramServices\OnecHelperHandlers\CollectMessageHandler;
use App\Services\TelegramServices\OnecHelperHandlers\StartMessageHandler;
use App\Services\TelegramServices\OnecHelperHandlers\UnsupportedMessageHandler;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

/**
 * The 1C helper bot: its owners forward to it the questions about the 1C base,
 * it gathers each into one question for the worker on the 1C server and sends
 * back the worker's answer. Only the owners of config('onec_helper.owner_ids')
 * are answered.
 */
class OnecHelperService extends BaseService
{
    protected function getUpdateHandlers(): array
    {
        $handlers = parent::getUpdateHandlers();

        $handlers['callback_query'] = fn () => app(CallbackQueryHandler::class, [
            'callbackQueryHandlers' => $this->getCallbackQueryHandlers(),
        ]);

        return $handlers;
    }

    protected function getMessageHandlers(): array
    {
        $handlers = parent::getMessageHandlers();

        foreach (['voice', 'audio', 'photo', 'document', 'video', 'animation'] as $type) {
            $handlers[$type] = fn () => app(CollectMessageHandler::class);
        }

        foreach (['video_note', 'sticker', 'location', 'venue', 'contact', 'poll', 'dice'] as $type) {
            $handlers[$type] = fn () => app(UnsupportedMessageHandler::class);
        }

        return $handlers;
    }

    protected function getTextMessageHandlers(): array
    {
        $handlers = parent::getTextMessageHandlers();

        $handlers['/start'] = fn () => app(StartMessageHandler::class);
        $handlers['default'] = fn () => app(CollectMessageHandler::class);

        return $handlers;
    }

    protected function getCallbackQueryHandlers(): array
    {
        $handlers = parent::getCallbackQueryHandlers();

        $handlers['onec'] = fn () => app(ButtonCallbackQueryHandler::class);

        return $handlers;
    }

    /**
     * Anyone but an owner gets nothing: the update is dropped.
     */
    public function handle(Bot $bot, Api $telegram, Update $update, ?BotUser $botUser): void
    {
        $type = $update->detectType();
        $fromId = $type === null ? null : data_get($update->toArray(), $type.'.from.id');

        if ($fromId === null || ! in_array((int) $fromId, config('onec_helper.owner_ids'), true)) {
            return;
        }

        parent::handle($bot, $telegram, $update, $botUser);
    }
}
