<?php

namespace App\Services\TelegramServices;

use App\Services\TelegramServices\CaloriesHandlers\AudioMessageHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\CallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\CancelCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\DeleteCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditActionCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditingProcessCallbackQuery\EditingCancelCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditingProcessCallbackQuery\EditingSaveCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditingProcessCallbackQuery\EditingSkipCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\SaveCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers\EditMessageHandler;

class CaloriesService extends BaseService
{
    protected function getUpdateHandlers(): array
    {
        $updateHandlers = parent::getUpdateHandlers();

        $updateHandlers['callback_query'] = new CallbackQueryHandler(
            new CancelCallbackQueryHandler(),
            new SaveCallbackQueryHandler(),
            new EditCallbackQueryHandler(),
            new DeleteCallbackQueryHandler(),
            new EditingSaveCallbackQueryHandler(),
            new EditingCancelCallbackQueryHandler(),
            new EditingSkipCallbackQueryHandler(),
        );

        return $updateHandlers;
    }


    protected function getMessageHandlers(): array
    {
        $messageHandlers = parent::getMessageHandlers();

        // Используем AudioMessageHandler для обработки голосовых сообщений
        $messageHandlers['voice'] = app(AudioMessageHandler::class);

        // Используем EditMessageHandler для обработки текстовых сообщений во время редактирования
        $messageHandlers['text'] = app(EditMessageHandler::class);

        return $messageHandlers;
    }

}
