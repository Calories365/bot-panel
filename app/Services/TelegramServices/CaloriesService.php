<?php

namespace App\Services\TelegramServices;

use App\Services\TelegramServices\CaloriesHandlers\AudioMessageHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\CallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\CancelCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\DeleteCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\DeleteMealCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditingProcessCallbackQuery\EditingCancelCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditingProcessCallbackQuery\EditingSaveCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditingProcessCallbackQuery\EditingSkipCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\LanguageCallbackHandlers\SetEnglishLanguageCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\LanguageCallbackHandlers\SetRussianLanguageCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\LanguageCallbackHandlers\SetUkrainianLanguageCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\SaveCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\SearchCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\StatsCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers\EditMessageHandler;
use App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers\FeedbackMessageHandler;
use App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers\LanguageMessageHandler;
use App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers\StartMessageHandler;
use App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers\StatsMessageHandler;
use App\Utilities\Utilities;

class CaloriesService extends BaseService
{
    protected bool $auth = true;

    protected array $excludedCommands = [
        '/start',
        '/language'
    ];

    public function getExcludedCommands(): array
    {
        return $this->excludedCommands;
    }

    protected function getUpdateHandlers(): array
    {
        $updateHandlers = parent::getUpdateHandlers();

        $updateHandlers['callback_query'] = app(CallbackQueryHandler::class, [
            'callbackQueryHandlers' => $this->getCallbackQueryHandlers()
        ]);

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

        $textMessageHandlers['/stats'] = app(StatsMessageHandler::class);

        $textMessageHandlers['/start'] = app(StartMessageHandler::class);

        $synonyms = [
            '/start' => ['Меню', 'Menu'],
        ];

        Utilities::applySynonyms($textMessageHandlers, $synonyms);

        $textMessageHandlers['/language'] = app(LanguageMessageHandler::class);

        $synonyms = [
            '/stats' => ['Statistics', 'Статистика'],
        ];
        Utilities::applySynonyms($textMessageHandlers, $synonyms);

        $synonyms = [
            '/language' => ['Choose language', 'Выбор языка', 'Вибір мови'],
        ];
        Utilities::applySynonyms($textMessageHandlers, $synonyms);

        $textMessageHandlers['feedback'] = app(FeedbackMessageHandler::class);

        $synonyms = [
            'feedback' => ['Feedback','Обратная связь','Зворотний зв\'язок',],
        ];

        Utilities::applySynonyms($textMessageHandlers, $synonyms);

        return $textMessageHandlers;
    }

    protected function getCallbackQueryHandlers(): array
    {
        $callbackQueryHandlers = parent::getCallbackQueryHandlers();

        $callbackQueryHandlers['cancel'] = app(CancelCallbackQueryHandler::class);
        $callbackQueryHandlers['save'] = app(SaveCallbackQueryHandler::class);
        $callbackQueryHandlers['edit'] = app(EditCallbackQueryHandler::class);
        $callbackQueryHandlers['destroy'] = app(DeleteCallbackQueryHandler::class);

        $callbackQueryHandlers['editing_save'] = app(EditingSaveCallbackQueryHandler::class);
        $callbackQueryHandlers['editing_cancel'] = app(EditingCancelCallbackQueryHandler::class);
        $callbackQueryHandlers['editing_skip'] = app(EditingSkipCallbackQueryHandler::class);

        $callbackQueryHandlers['search'] = app(SearchCallbackQueryHandler::class);
        $callbackQueryHandlers['delete_meal'] = app(DeleteMealCallbackQueryHandler::class);

        $callbackQueryHandlers['English'] = app(SetEnglishLanguageCallbackQueryHandler::class);
        $callbackQueryHandlers['Russian'] = app(SetRussianLanguageCallbackQueryHandler::class);
        $callbackQueryHandlers['Ukrainian'] = app(SetUkrainianLanguageCallbackQueryHandler::class);

        $callbackQueryHandlers['Stats'] = app(StatsCallbackQueryHandler::class);

        $synonyms = [
            'Stats' => ['Breakfast', 'Dinner', 'Supper', 'AllDay'],
        ];

        Utilities::applySynonyms($callbackQueryHandlers, $synonyms);

        return $callbackQueryHandlers;
    }
}
