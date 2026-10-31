<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use App\Services\DiaryApiService;
use App\Services\TelegramServices\CaloriesHandlers\MealStatsText;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DeleteMealCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    public bool $blockAble = true;

    protected DiaryApiService $diaryApiService;

    public function __construct(DiaryApiService $diaryApiService)
    {
        $this->diaryApiService = $diaryApiService;
    }

    public function handle($bot, $telegram, $callbackQuery, $botUser)
    {
        $callbackData = $callbackQuery->getData();
        $parts = explode('_', $callbackData);

        $locale = $botUser->locale;
        $calories_id = $botUser->calories_id;

        if (isset($parts[2])) {
            $mealId = $parts[2];

            $chatId = $callbackQuery->getMessage()->getChat()->getId();
            $messageId = $callbackQuery->getMessage()->getMessageId();

            $response = $this->diaryApiService->deleteMeal($mealId, $calories_id, $locale);
            if (isset($response['error'])) {
                Log::error('Error deleting meal: '.$response['error']);
                $telegram->answerCallbackQuery([
                    'callback_query_id' => $callbackQuery->getId(),
                    'text' => __('calories365-bot.error_deleting_product'),
                    'show_alert' => true,
                ]);
            } else {
                try {
                    $telegram->deleteMessage([
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error deleting meal message: '.$e->getMessage());
                }

                $telegram->answerCallbackQuery([
                    'callback_query_id' => $callbackQuery->getId(),
                    'text' => __('calories365-bot.product_deleted'),
                    'show_alert' => false,
                ]);

                $cacheKey = "stats_summary_{$chatId}";
                $cacheValue = Cache::get($cacheKey);

                if ($cacheValue) {
                    $date = $cacheValue['date'];
                    $partOfDay = $cacheValue['part_of_day'];
                    $finalMessageId = $cacheValue['final_message_id'];
                    $cachedLocale = $cacheValue['locale'] ?? $locale;

                    $meals = $this->diaryApiService->showUserStats($date, $partOfDay, $calories_id, $cachedLocale);

                    // The diary did not answer: the summary stays as it was.
                    if (isset($meals['error'])) {
                        return;
                    }

                    if (empty($meals)) {

                        try {
                            $telegram->editMessageText([
                                'chat_id' => $chatId,
                                'message_id' => $finalMessageId,
                                'text' => __('calories365-bot.no_entries_remain'), // Добавьте перевод
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Error editing final stats message: '.$e->getMessage());
                        }

                        Cache::forget($cacheKey);
                    } else {
                        $useBigFont = (bool) ($botUser->big_font ?? false);
                        $newText = $this->generateUpdatedStatsText($meals, $date, $partOfDay, $cachedLocale, $useBigFont);

                        try {
                            $telegram->editMessageText([
                                'chat_id' => $chatId,
                                'message_id' => $finalMessageId,
                                'text' => $newText,
                                'parse_mode' => 'Markdown',
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Error editing final stats message: '.$e->getMessage());
                        }
                    }
                }
            }
        }
    }

    private function generateUpdatedStatsText(array $meals, string $date, ?string $partOfDay, string $locale, bool $useBigFont): string
    {
        if ($partOfDay) {
            $total = [
                'calories' => 0,
                'proteins' => 0,
                'fats' => 0,
                'carbohydrates' => 0,
            ];
            foreach ($meals as $meal) {
                $quantityFactor = $meal['quantity'] / 100;
                $total['calories'] += $meal['calories'] * $quantityFactor;
                $total['proteins'] += $meal['proteins'] * $quantityFactor;
                $total['fats'] += $meal['fats'] * $quantityFactor;
                $total['carbohydrates'] += $meal['carbohydrates'] * $quantityFactor;
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

            return $useBigFont
                ? \App\Utilities\Utilities::generateTableType2ForBigFont(
                    __('calories365-bot.total_for_part_of_day', [
                        'partOfDayName' => $partOfDayName,
                    ], $locale),
                    $productArray
                )
                : \App\Utilities\Utilities::generateTableType2(
                    __('calories365-bot.total_for_part_of_day', [
                        'partOfDayName' => $partOfDayName,
                    ], $locale),
                    $productArray
                );
        }

        return MealStatsText::allDay($meals, $date, $locale, $useBigFont);
    }
}
