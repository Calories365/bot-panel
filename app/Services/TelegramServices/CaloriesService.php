<?php

namespace App\Services\TelegramServices;

use App\Services\ChatGPTServices\SpeechToTextService;
use App\Services\TelegramServices\BaseHandlers\TextMessageHandlers\StartMessageHandler;
use App\Services\TelegramServices\CaloriesHandlers\AudioMessageHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\CallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\CancelCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\DeleteCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditingProcessCallbackQuery\EditingCancelCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditingProcessCallbackQuery\EditingSaveCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditingProcessCallbackQuery\EditingSkipCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\GenerateCallbackQueryHandler;
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
            new GenerateCallbackQueryHandler(
                new SpeechToTextService()
            ),
        );

        return $updateHandlers;
    }


    protected function getMessageHandlers(): array
    {
        $messageHandlers = parent::getMessageHandlers();

        $messageHandlers['voice'] = app(AudioMessageHandler::class);

        return $messageHandlers;
    }

    protected function getTextMessageHandlers(): array
    {
        $textMessageHandlers = parent::getTextMessageHandlers();

        $textMessageHandlers['default'] = app(EditMessageHandler::class);

        return $textMessageHandlers;
    }

}
