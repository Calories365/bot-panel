<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Services\DiaryApiService;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Keyboard\Keyboard;

class StatsMessageHandler
{
    protected DiaryApiService $diaryApiService;

    public function __construct(DiaryApiService $diaryApiService)
    {
        $this->diaryApiService = $diaryApiService;
    }

    public function handle($bot, $telegram, $message, $botUser)
    {
        $text = $message->getText();
        $chatId = $message->getChat()->getId();

        // Локаль
        $locale = $botUser->locale ?? 'ru'; // или любой другой fallback

        $calories_id = $botUser->calories_id;

        if (!$botUser) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => __('calories365-bot.auth_required', [], $locale)
            ]);
            return;
        }

        if (str_contains($text, '/stats')) {
            $userId = $message->getFrom()->getId();

            $date = date('Y-m-d');
            $partOfDay = null;
            $commandParts = explode('_', $text);
            if (isset($commandParts[1])) {
                $partOfDay = $commandParts[1];
            }

            $responseArray = $this->diaryApiService->showUserStats($date, $partOfDay, $calories_id, $locale);

            if (isset($responseArray['error'])) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => __('calories365-bot.error_retrieving_data', [], $locale),
                ]);
                return;
            }

            if (!$partOfDay) {
                Log::info($partOfDay);
            }

            if ($partOfDay) {
                $messageText = $this->formatStatsMessage($responseArray, $date, $partOfDay, $chatId, $telegram, $locale);
            } else {
                $messageText = $this->formatTotalStatsMessage($responseArray, $date, $locale);
            }

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $messageText,
                'parse_mode' => 'Markdown',
            ]);
        }
    }

    protected function formatTotalStatsMessage($meals, $date, $locale)
    {
        // Если нет записей
        if (empty($meals)) {
            return __('calories365-bot.no_entries_for_date', ['date' => $date], $locale);
        }

        // Используем переводы для названий приёмов пищи
        $partsOfDay = [
            'morning' => [
                'name' => __('calories365-bot.breakfast', [], $locale),
                'calories' => 0,
                'proteins' => 0,
                'fats' => 0,
                'carbohydrates' => 0,
            ],
            'dinner' => [
                'name' => __('calories365-bot.lunch', [], $locale),
                'calories' => 0,
                'proteins' => 0,
                'fats' => 0,
                'carbohydrates' => 0,
            ],
            'supper' => [
                'name' => __('calories365-bot.dinner', [], $locale),
                'calories' => 0,
                'proteins' => 0,
                'fats' => 0,
                'carbohydrates' => 0,
            ],
        ];

        $total = [
            'calories' => 0,
            'proteins' => 0,
            'fats' => 0,
            'carbohydrates' => 0,
        ];

        foreach ($meals as $meal) {
            $part = $meal['part_of_day'];
            if (!isset($partsOfDay[$part])) {
                continue;
            }

            $quantityFactor = $meal['quantity'] / 100;

            $calories = $meal['calories'] * $quantityFactor;
            $proteins = $meal['proteins'] * $quantityFactor;
            $fats = $meal['fats'] * $quantityFactor;
            $carbohydrates = $meal['carbohydrates'] * $quantityFactor;

            $partsOfDay[$part]['calories'] += $calories;
            $partsOfDay[$part]['proteins'] += $proteins;
            $partsOfDay[$part]['fats'] += $fats;
            $partsOfDay[$part]['carbohydrates'] += $carbohydrates;

            $total['calories'] += $calories;
            $total['proteins'] += $proteins;
            $total['fats'] += $fats;
            $total['carbohydrates'] += $carbohydrates;
        }

        // Заголовок «Ваши данные за *:date*:»
        $messageText = __('calories365-bot.your_data_for_date', ['date' => $date], $locale) . "\n\n";

        // Вывод статистики по утрам/обедам/ужинам
        foreach ($partsOfDay as $part) {
            if ($part['calories'] == 0) {
                continue;
            }
            $productArray = [
                [ __('calories365-bot.calories', [], $locale), round($part['calories']) ],
                [ __('calories365-bot.proteins', [], $locale), round($part['proteins']) ],
                [ __('calories365-bot.fats', [], $locale), round($part['fats']) ],
                [ __('calories365-bot.carbohydrates', [], $locale), round($part['carbohydrates']) ],
            ];
            $messageText .= Utilities::generateTableType2($part['name'], $productArray) . "\n\n";
        }

        // Итог
        $productArray = [
            [ __('calories365-bot.calories', [], $locale), round($total['calories']) ],
            [ __('calories365-bot.proteins', [], $locale), round($total['proteins']) ],
            [ __('calories365-bot.fats', [], $locale), round($total['fats']) ],
            [ __('calories365-bot.carbohydrates', [], $locale), round($total['carbohydrates']) ],
        ];

        // «Итого за день»
        $messageText .= Utilities::generateTableType2(
            __('calories365-bot.total_for_day', [], $locale),
            $productArray
        );

        return $messageText;
    }

    protected function formatStatsMessage($meals, $date, $partOfDay, $chatId, $telegram, $locale)
    {
        if (empty($meals)) {
            // Текст «за *{$partOfDay}*» или «на дату *{$date}*»
            $partOfDayText = $partOfDay ? "*{$partOfDay}*" : "*{$date}*";
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => __('calories365-bot.no_entries_for_part_of_day', ['partOfDayText' => $partOfDayText], $locale),
                'parse_mode' => 'Markdown',
            ]);
            return;
        }

        $total = [
            'calories' => 0,
            'proteins' => 0,
            'fats' => 0,
            'carbohydrates' => 0,
        ];

        // Выводим каждую добавленную еду
        foreach ($meals as $meal) {
            $quantityFactor = $meal['quantity'] / 100;

            $calories = $meal['calories'] * $quantityFactor;
            $proteins = $meal['proteins'] * $quantityFactor;
            $fats = $meal['fats'] * $quantityFactor;
            $carbohydrates = $meal['carbohydrates'] * $quantityFactor;

            $total['calories'] += $calories;
            $total['proteins'] += $proteins;
            $total['fats'] += $fats;
            $total['carbohydrates'] += $carbohydrates;

            $productArray = [
                [ __('calories365-bot.calories', [], $locale), round($calories) ],
                [ __('calories365-bot.proteins', [], $locale), round($proteins) ],
                [ __('calories365-bot.fats', [], $locale), round($fats) ],
                [ __('calories365-bot.carbohydrates', [], $locale), round($carbohydrates) ],
            ];

            $messageText = Utilities::generateTableType2(
                $meal['name'] . " ({$meal['quantity']}г)",
                $productArray
            );

            // Кнопка «Удалить» => перевод
            $inlineKeyboard = [
                [
                    [
                        'text' => __('calories365-bot.delete', [], $locale),
                        'callback_data' => 'delete_meal_' . $meal['id']
                    ]
                ]
            ];

            $replyMarkup = json_encode(['inline_keyboard' => $inlineKeyboard]);

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $messageText,
                'parse_mode' => 'Markdown',
                'reply_markup' => $replyMarkup,
            ]);
        }

        // Итоговая сводка по выбранной части дня
        $productArray = [
            [ __('calories365-bot.calories', [], $locale), round($total['calories']) ],
            [ __('calories365-bot.proteins', [], $locale), round($total['proteins']) ],
            [ __('calories365-bot.fats', [], $locale), round($total['fats']) ],
            [ __('calories365-bot.carbohydrates', [], $locale), round($total['carbohydrates']) ],
        ];

        $partOfDayName = $this->getPartOfDayName($partOfDay, $locale);
        $messageText = Utilities::generateTableType2(
            __('calories365-bot.total_for_part_of_day', ['partOfDayName' => $partOfDayName], $locale),
            $productArray
        );

        return $messageText;
    }

    /**
     * Преобразуем часть дня в переведённую строку (Завтрак/Обед/Ужин)
     */
    private function getPartOfDayName($partOfDay, $locale)
    {
        switch ($partOfDay) {
            case 'morning':
                return __('calories365-bot.breakfast', [], $locale);
            case 'dinner':
                return __('calories365-bot.lunch', [], $locale);
            case 'supper':
                return __('calories365-bot.dinner', [], $locale);
            default:
                // Если вдруг нет совпадения — «день» или как-то иначе
                return __('calories365-bot.total_for_day', [], $locale);
        }
    }
}
