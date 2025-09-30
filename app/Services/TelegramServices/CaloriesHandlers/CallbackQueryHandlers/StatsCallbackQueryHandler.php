<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use App\Services\DiaryApiService;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $messageId = $callbackQuery->getMessage()->getMessageId();
        $locale = $botUser->locale ?? 'ru';

        try {
            $telegram->deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting stats message: '.$e->getMessage());
        }

        $telegram->answerCallbackQuery([
            'callback_query_id' => $callbackQuery->getId(),
        ]);

        if (! $botUser || ! $botUser->calories_id) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => __('calories365-bot.auth_required', [], $locale),
            ]);

            return;
        }

        $callbackData = $callbackQuery->getData();
        $dayPartMap = [
            'Breakfast' => 'morning',
            'Dinner' => 'dinner',
            'Supper' => 'supper',
            'AllDay' => null,
        ];

        $partOfDay = $dayPartMap[$callbackData] ?? null;

        $date = date('Y-m-d');
        $meals = $this->diaryApiService->showUserStats($date, $partOfDay, $botUser->calories_id, $locale);

        if (empty($meals)) {
            $this->sendNoEntriesMessage($telegram, $chatId, $partOfDay, $locale);

            return;
        }

        $useBigFont = (bool) ($botUser->big_font ?? false);

        if ($partOfDay) {
            $this->formatAndSendPartOfDay($telegram, $chatId, $meals, $date, $partOfDay, $locale, $useBigFont);
        } else {
            $this->formatAndSendAllDay($telegram, $chatId, $meals, $date, $locale, $useBigFont);
        }
    }

    private function formatAndSendPartOfDay($telegram, $chatId, $meals, $date, $partOfDay, $locale, bool $useBigFont)
    {
        $total = [
            'calories' => 0,
            'proteins' => 0,
            'fats' => 0,
            'carbohydrates' => 0,
        ];

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
                [__('calories365-bot.calories', [], $locale), round($calories)],
                [__('calories365-bot.proteins', [], $locale), round($proteins)],
                [__('calories365-bot.fats', [], $locale), round($fats)],
                [__('calories365-bot.carbohydrates', [], $locale), round($carbohydrates)],
            ];

            $mealMessage = $useBigFont
                ? Utilities::generateTableType2ForBigFont($meal['name']." ({$meal['quantity']}г)", $productArray)
                : Utilities::generateTableType2($meal['name']." ({$meal['quantity']}г)", $productArray);

            $inlineKeyboard = [
                [
                    [
                        'text' => __('calories365-bot.delete', [], $locale),
                        'callback_data' => 'delete_meal_'.$meal['id'],
                    ],
                ],
            ];

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $mealMessage,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard]),
            ]);
        }

        $productArray = [
            [__('calories365-bot.calories', [], $locale), round($total['calories'])],
            [__('calories365-bot.proteins', [], $locale), round($total['proteins'])],
            [__('calories365-bot.fats', [], $locale), round($total['fats'])],
            [__('calories365-bot.carbohydrates', [], $locale), round($total['carbohydrates'])],
        ];

        $partOfDayName = match ($partOfDay) {
            'morning' => __('calories365-bot.breakfast', [], $locale),
            'dinner' => __('calories365-bot.lunch', [], $locale),
            'supper' => __('calories365-bot.dinner', [], $locale),
            default => __('calories365-bot.total_for_day', [], $locale)
        };

        $finalMessageText = $useBigFont
            ? Utilities::generateTableType2ForBigFont(__('calories365-bot.total_for_part_of_day', ['partOfDayName' => $partOfDayName], $locale), $productArray)
            : Utilities::generateTableType2(__('calories365-bot.total_for_part_of_day', ['partOfDayName' => $partOfDayName], $locale), $productArray);

        $sent = $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $finalMessageText,
            'parse_mode' => 'Markdown',
        ]);

        $finalMessageId = $sent->getMessageId();

        Cache::put("stats_summary_{$chatId}", [
            'date' => $date,
            'part_of_day' => $partOfDay,
            'final_message_id' => $finalMessageId,
            'locale' => $locale,
        ], 1800);
    }

    private function formatAndSendAllDay($telegram, $chatId, $meals, $date, $locale, bool $useBigFont)
    {

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
            if (! isset($partsOfDay[$part])) {
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

        $messageText = __('calories365-bot.your_data_for_date', ['date' => $date], $locale)."\n\n";
        foreach ($partsOfDay as $partKey => $part) {
            if ($part['calories'] == 0 && $part['proteins'] == 0 && $part['fats'] == 0 && $part['carbohydrates'] == 0) {
                continue;
            }

            $productArray = [
                [__('calories365-bot.calories', [], $locale), round($part['calories'])],
                [__('calories365-bot.proteins', [], $locale), round($part['proteins'])],
                [__('calories365-bot.fats', [], $locale), round($part['fats'])],
                [__('calories365-bot.carbohydrates', [], $locale), round($part['carbohydrates'])],
            ];

            $messageText .= ($useBigFont
                ? Utilities::generateTableType2ForBigFont($part['name'], $productArray)
                : Utilities::generateTableType2($part['name'], $productArray)
            )."\n\n";
        }

        $productArray = [
            [__('calories365-bot.calories', [], $locale), round($total['calories'])],
            [__('calories365-bot.proteins', [], $locale), round($total['proteins'])],
            [__('calories365-bot.fats', [], $locale), round($total['fats'])],
            [__('calories365-bot.carbohydrates', [], $locale), round($total['carbohydrates'])],
        ];

        $messageText .= ($useBigFont
            ? Utilities::generateTableType2ForBigFont(__('calories365-bot.total_for_day', [], $locale), $productArray)
            : Utilities::generateTableType2(__('calories365-bot.total_for_day', [], $locale), $productArray)
        );

        $sent = $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $messageText,
            'parse_mode' => 'Markdown',
        ]);

        $finalMessageId = $sent->getMessageId();

        Cache::put("stats_summary_{$chatId}", [
            'date' => $date,
            'part_of_day' => null,
            'final_message_id' => $finalMessageId,
            'locale' => $locale,
        ], 1800);
    }

    private function sendNoEntriesMessage($telegram, $chatId, $partOfDay, $locale)
    {
        if ($partOfDay) {
            $partOfDayName = match ($partOfDay) {
                'morning' => __('calories365-bot.breakfast', [], $locale),
                'dinner' => __('calories365-bot.lunch', [], $locale),
                'supper' => __('calories365-bot.dinner', [], $locale),
                default => __('calories365-bot.total_for_day', [], $locale)
            };

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => __('calories365-bot.no_entries_for_part_of_day', [
                    'partOfDayText' => $partOfDayName,
                ], $locale),
                'parse_mode' => 'Markdown',
            ]);
        } else {
            $date = date('Y-m-d');
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => __('calories365-bot.no_entries_for_date', ['date' => $date], $locale),
            ]);
        }
    }
}
