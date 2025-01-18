<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use App\Services\DiaryApiService;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class StatsCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    public bool $blockAble = false;

    protected DiaryApiService $diaryApiService;

    public function __construct(DiaryApiService $diaryApiService)
    {
        $this->diaryApiService = $diaryApiService;
    }

    public function handle($bot, $telegram, $callbackQuery, $botUser)
    {
        $chatId    = $callbackQuery->getMessage()->getChat()->getId();
        $messageId = $callbackQuery->getMessage()->getMessageId();
        $locale    = $botUser->locale ?? 'ru';

        // Удаляем исходное сообщение с кнопками "Breakfast", "Dinner" и т.д.
        try {
            $telegram->deleteMessage([
                'chat_id'    => $chatId,
                'message_id' => $messageId,
            ]);
        } catch (\Exception $e) {
            Log::error("Error deleting stats message: " . $e->getMessage());
        }

        $telegram->answerCallbackQuery([
            'callback_query_id' => $callbackQuery->getId(),
        ]);

        if (!$botUser || !$botUser->calories_id) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => __('calories365-bot.auth_required', [], $locale),
            ]);
            return;
        }

        $callbackData = $callbackQuery->getData();
        $dayPartMap   = [
            'Breakfast' => 'morning',
            'Dinner'    => 'dinner',
            'Supper'    => 'supper',
            'AllDay'    => null,
        ];

        $partOfDay = $dayPartMap[$callbackData] ?? null;

        $date  = date('Y-m-d'); // Если нужно — берём текущую дату
        $meals = $this->diaryApiService->showUserStats($date, $partOfDay, $botUser->calories_id, $locale);

        if (empty($meals)) {
            $this->sendNoEntriesMessage($telegram, $chatId, $partOfDay, $locale);
            return;
        }

        // В зависимости от наличия partOfDay — показываем статистику либо за часть дня, либо за весь день.
        if ($partOfDay) {
            $this->formatAndSendPartOfDay($telegram, $chatId, $meals, $date, $partOfDay, $locale);
        } else {
            $this->formatAndSendAllDay($telegram, $chatId, $meals, $date, $locale);
        }
    }

    private function formatAndSendPartOfDay($telegram, $chatId, $meals, $date, $partOfDay, $locale)
    {
        $total = [
            'calories' => 0,
            'proteins' => 0,
            'fats' => 0,
            'carbohydrates' => 0,
        ];

        // 1) Сначала выводим каждую запись отдельным сообщением
        //    (тут, как и у вас сейчас, с кнопками "Удалить").
        foreach ($meals as $meal) {
            $quantityFactor = $meal['quantity'] / 100;

            $calories      = $meal['calories']      * $quantityFactor;
            $proteins      = $meal['proteins']      * $quantityFactor;
            $fats          = $meal['fats']          * $quantityFactor;
            $carbohydrates = $meal['carbohydrates'] * $quantityFactor;

            $total['calories']      += $calories;
            $total['proteins']      += $proteins;
            $total['fats']          += $fats;
            $total['carbohydrates'] += $carbohydrates;

            $productArray = [
                [ __('calories365-bot.calories', [], $locale), round($calories) ],
                [ __('calories365-bot.proteins', [], $locale), round($proteins) ],
                [ __('calories365-bot.fats', [], $locale), round($fats) ],
                [ __('calories365-bot.carbohydrates', [], $locale), round($carbohydrates) ],
            ];

            $mealMessage = Utilities::generateTableType2(
                $meal['name'] . " ({$meal['quantity']}г)",
                $productArray
            );

            $inlineKeyboard = [
                [
                    [
                        'text'          => __('calories365-bot.delete', [], $locale),
                        'callback_data' => 'delete_meal_' . $meal['id'] // обратите внимание, формат такой же
                    ]
                ]
            ];

            $telegram->sendMessage([
                'chat_id'      => $chatId,
                'text'         => $mealMessage,
                'parse_mode'   => 'Markdown',
                'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard]),
            ]);
        }

        // 2) Затем одним сообщением выводим ИТОГО по этой части дня
        $productArray = [
            [ __('calories365-bot.calories', [], $locale), round($total['calories']) ],
            [ __('calories365-bot.proteins', [], $locale), round($total['proteins']) ],
            [ __('calories365-bot.fats', [], $locale), round($total['fats']) ],
            [ __('calories365-bot.carbohydrates', [], $locale), round($total['carbohydrates']) ],
        ];

        $partOfDayName = match ($partOfDay) {
            'morning' => __('calories365-bot.breakfast', [], $locale),
            'dinner'  => __('calories365-bot.lunch', [], $locale),
            'supper'  => __('calories365-bot.dinner', [], $locale),
            default   => __('calories365-bot.total_for_day', [], $locale)
        };

        $finalMessageText  = Utilities::generateTableType2(
            __('calories365-bot.total_for_part_of_day', ['partOfDayName' => $partOfDayName], $locale),
            $productArray
        );

        // <-- ВАЖНО: надо сохранить результат вызова sendMessage,
        // чтобы получить message_id.
        $sent = $telegram->sendMessage([
            'chat_id'    => $chatId,
            'text'       => $finalMessageText,
            'parse_mode' => 'Markdown',
        ]);

        // Сохраняем в кэше (ключ можно придумать любой)
        // Обратите внимание, что $sent — это объект, который Telegram SDK возвращает (Message).
        $finalMessageId = $sent->getMessageId();

        // Например, кешируем на 30 минут
        Cache::put("stats_summary_{$chatId}", [
            'date'              => $date,
            'part_of_day'       => $partOfDay,
            'final_message_id'  => $finalMessageId,
            'locale'            => $locale,
        ], 1800);
    }

    private function formatAndSendAllDay($telegram, $chatId, $meals, $date, $locale)
    {
        // Логика аналогична: выводим всё по частям, считаем итоги,
        // а в конце одним сообщением — итог.
        // В конце делаем то же самое: сохраняем message_id в кэше.

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

        // Если хотим по каждому продукту отдавать отдельное сообщение — можно это сделать
        // тут тоже (как в части formatAndSendPartOfDay).
        foreach ($meals as $meal) {
            // ... Если хотите показывать каждую запись (по аналогии) ...
            // Но обычно "AllDay" показывают итогово, без отдельных сообщений.

            // Подсчитываем в нужной части дня:
            $part = $meal['part_of_day'];
            if (!isset($partsOfDay[$part])) {
                continue;
            }
            $quantityFactor = $meal['quantity'] / 100;

            $calories      = $meal['calories']      * $quantityFactor;
            $proteins      = $meal['proteins']      * $quantityFactor;
            $fats          = $meal['fats']          * $quantityFactor;
            $carbohydrates = $meal['carbohydrates'] * $quantityFactor;

            $partsOfDay[$part]['calories']      += $calories;
            $partsOfDay[$part]['proteins']      += $proteins;
            $partsOfDay[$part]['fats']          += $fats;
            $partsOfDay[$part]['carbohydrates'] += $carbohydrates;

            $total['calories']      += $calories;
            $total['proteins']      += $proteins;
            $total['fats']          += $fats;
            $total['carbohydrates'] += $carbohydrates;
        }

        $messageText = __('calories365-bot.your_data_for_date', ['date' => $date], $locale) . "\n\n";
        // Формируем вывод для утро/обед/ужин
        foreach ($partsOfDay as $partKey => $part) {
            if ($part['calories'] == 0 && $part['proteins'] == 0 && $part['fats'] == 0 && $part['carbohydrates'] == 0) {
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

        // Итог по всему дню
        $productArray = [
            [ __('calories365-bot.calories', [], $locale), round($total['calories']) ],
            [ __('calories365-bot.proteins', [], $locale), round($total['proteins']) ],
            [ __('calories365-bot.fats', [], $locale), round($total['fats']) ],
            [ __('calories365-bot.carbohydrates', [], $locale), round($total['carbohydrates']) ],
        ];

        $messageText .= Utilities::generateTableType2(
            __('calories365-bot.total_for_day', [], $locale),
            $productArray
        );

        // <-- сохраняем MessageId
        $sent = $telegram->sendMessage([
            'chat_id'    => $chatId,
            'text'       => $messageText,
            'parse_mode' => 'Markdown',
        ]);

        $finalMessageId = $sent->getMessageId();

        // Кладём в кэш
        Cache::put("stats_summary_{$chatId}", [
            'date'              => $date,
            'part_of_day'       => null, // null означает, что показывали весь день
            'final_message_id'  => $finalMessageId,
            'locale'            => $locale,
        ], 1800);
    }

    private function sendNoEntriesMessage($telegram, $chatId, $partOfDay, $locale)
    {
        if ($partOfDay) {
            $partOfDayName = match ($partOfDay) {
                'morning' => __('calories365-bot.breakfast', [], $locale),
                'dinner'  => __('calories365-bot.lunch', [], $locale),
                'supper'  => __('calories365-bot.dinner', [], $locale),
                default   => __('calories365-bot.total_for_day', [], $locale)
            };

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => __('calories365-bot.no_entries_for_part_of_day', [
                    'partOfDayText' => $partOfDayName
                ], $locale),
                'parse_mode' => 'Markdown',
            ]);
        } else {
            $date = date('Y-m-d');
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => __('calories365-bot.no_entries_for_date', ['date' => $date], $locale),
            ]);
        }
    }
}

