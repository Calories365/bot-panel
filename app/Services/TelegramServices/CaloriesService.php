<?php

namespace App\Services\TelegramServices;

use App\Services\TelegramServices\CaloriesHandlers\AudioMessageHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\BigFontCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\CallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\DeleteMealCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\DraftCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\InsulinCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\LanguageCallbackHandlers\SetEnglishLanguageCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\LanguageCallbackHandlers\SetRussianLanguageCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\LanguageCallbackHandlers\SetUkrainianLanguageCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\OutdatedCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\StatsCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\DocumentMessageHandler;
use App\Services\TelegramServices\CaloriesHandlers\PhotoMessageHandler;
use App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers\DraftTextMessageHandler;
use App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers\FeedbackMessageHandler;
use App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers\FontMessageHandler;
use App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers\HelpMessageHandler;
use App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers\InsulinMessageHandler;
use App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers\LanguageMessageHandler;
use App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers\StartMessageHandler;
use App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers\StatsMessageHandler;
use App\Services\TelegramServices\CaloriesHandlers\UnsupportedMessageHandler;
use App\Utilities\Utilities;

class CaloriesService extends BaseService
{
    protected bool $auth = true;

    protected array $excludedCommands = [
        '/start',
        'Menu',
        'Меню',
    ];

    public function getExcludedCommands(): array
    {
        return $this->excludedCommands;
    }

    protected function getUpdateHandlers(): array
    {
        $handlers = parent::getUpdateHandlers();

        $handlers['callback_query'] = function () {
            return app(CallbackQueryHandler::class, [
                'callbackQueryHandlers' => $this->getCallbackQueryHandlers(),
            ]);
        };

        return $handlers;
    }

    protected function getMessageHandlers(): array
    {
        $handlers = parent::getMessageHandlers();

        $handlers['voice'] = fn () => app(AudioMessageHandler::class);
        $handlers['audio'] = fn () => app(AudioMessageHandler::class);
        $handlers['photo'] = fn () => app(PhotoMessageHandler::class);
        $handlers['document'] = fn () => app(DocumentMessageHandler::class);

        foreach (['video', 'video_note', 'sticker', 'animation', 'location', 'contact', 'poll', 'dice'] as $type) {
            $handlers[$type] = fn () => app(UnsupportedMessageHandler::class);
        }

        return $handlers;
    }

    protected function getTextMessageHandlers(): array
    {
        $h = parent::getTextMessageHandlers();

        $h['default'] = fn () => app(DraftTextMessageHandler::class);
        $h['/stats'] = fn () => app(StatsMessageHandler::class);
        $h['/help'] = fn () => app(HelpMessageHandler::class);
        $h['/insulin'] = fn () => app(InsulinMessageHandler::class);
        $h['/start'] = fn () => app(StartMessageHandler::class);
        $h['/language'] = fn () => app(LanguageMessageHandler::class);
        $h['font'] = fn () => app(FontMessageHandler::class);
        $h['feedback'] = fn () => app(FeedbackMessageHandler::class);

        Utilities::applySynonyms($h, [
            '/start' => ['Меню', 'Menu'],
            '/stats' => ['Statistics', 'Статистика'],
            '/help' => ['Как пользоваться', 'Як користуватися', 'How to use'],
            '/insulin' => ['📈 Инсулин', '📈 Інсулін', '📈 Insulin', 'Инсулин', 'Інсулін', 'Insulin'],
            '/language' => ['Choose language', 'Выбор языка', 'Вибір мови'],
            'feedback' => ['Feedback', 'Обратная связь', 'Зворотний зв\'язок'],
            'font' => ['Font', 'font', 'Шрифт', 'шрифт', 'Большой шрифт', 'Великий шрифт', 'Large font', 'Big font'],
        ]);

        return $h;
    }

    protected function getCallbackQueryHandlers(): array
    {
        $h = parent::getCallbackQueryHandlers();

        $h['draft'] = fn () => app(DraftCallbackQueryHandler::class);
        $h['delete_meal'] = fn () => app(DeleteMealCallbackQueryHandler::class);
        $h['insulin'] = fn () => app(InsulinCallbackQueryHandler::class);

        // Buttons of the per-product messages sent before the meal draft.
        foreach (['save', 'cancel', 'edit', 'destroy', 'search', 'editing_save', 'editing_cancel', 'editing_skip'] as $outdated) {
            $h[$outdated] = fn () => app(OutdatedCallbackQueryHandler::class);
        }

        $h['English'] = fn () => app(SetEnglishLanguageCallbackQueryHandler::class);
        $h['Russian'] = fn () => app(SetRussianLanguageCallbackQueryHandler::class);
        $h['Ukrainian'] = fn () => app(SetUkrainianLanguageCallbackQueryHandler::class);

        $h['Stats'] = fn () => app(StatsCallbackQueryHandler::class);
        $h['bigfont'] = fn () => app(BigFontCallbackQueryHandler::class);

        Utilities::applySynonyms($h, [
            'Stats' => ['Breakfast', 'Dinner', 'Supper', 'AllDay'],
        ]);

        return $h;
    }
}
