<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use App\Services\DiaryApiService;
use App\Services\TelegramServices\CaloriesHandlers\MealStatsText;
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

        if (isset($meals['error'])) {
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => __('calories365-bot.draft_failed', [], $locale)]);

            return;
        }

        if (empty($meals)) {
            $this->sendNoEntriesMessage($telegram, $chatId, $partOfDay, $locale);

            return;
        }

        $useBigFont = (bool) ($botUser->big_font ?? false);

        if ($partOfDay) {
            $this->formatAndSendPartOfDay($telegram, $chatId, $meals, $date, $partOfDay, $locale, $useBigFont);
        } else {
            $this->formatAndSendAllDay($telegram, $chatId, $meals, $date, $locale, $useBigFont, $botUser->calories_id);
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

            $title = MealStatsText::productTitle($meal, $locale);
            $mealMessage = $useBigFont
                ? Utilities::generateTableType2ForBigFont($title, $productArray)
                : Utilities::generateTableType2($title, $productArray);

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

    private function formatAndSendAllDay($telegram, $chatId, $meals, $date, $locale, bool $useBigFont, $caloriesId = null)
    {
        $messageText = MealStatsText::allDay($meals, $date, $locale, $useBigFont);

        // Protein, fibre, glycemic load and sugar of the day, when the diary answers.
        $day = $caloriesId ? $this->diaryApiService->insulinDay($date, $caloriesId, $locale, false) : ['error' => true];
        if (! isset($day['error']) && isset($day['summary'], $day['glucose'])) {
            $withDay = $messageText."\n\n".MealStatsText::beyondCalories($day, $locale);
            // Telegram refuses a message over 4096 characters.
            $messageText = mb_strlen($withDay) <= 4090 ? $withDay : $messageText;
        }

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
