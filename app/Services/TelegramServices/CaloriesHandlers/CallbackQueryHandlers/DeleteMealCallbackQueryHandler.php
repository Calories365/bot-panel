<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use App\Services\DiaryApiService;
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
        } else {
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
                $factor = $meal['quantity'] / 100;
                $cal = $meal['calories'] * $factor;
                $prot = $meal['proteins'] * $factor;
                $fat = $meal['fats'] * $factor;
                $carb = $meal['carbohydrates'] * $factor;

                $partsOfDay[$part]['calories'] += $cal;
                $partsOfDay[$part]['proteins'] += $prot;
                $partsOfDay[$part]['fats'] += $fat;
                $partsOfDay[$part]['carbohydrates'] += $carb;

                $total['calories'] += $cal;
                $total['proteins'] += $prot;
                $total['fats'] += $fat;
                $total['carbohydrates'] += $carb;
            }

            $text = __('calories365-bot.your_data_for_date', ['date' => $date], $locale)."\n\n";
            foreach ($partsOfDay as $p) {
                if ($p['calories'] == 0 && $p['proteins'] == 0 && $p['fats'] == 0 && $p['carbohydrates'] == 0) {
                    continue;
                }

                $table = [
                    [__('calories365-bot.calories', [], $locale), round($p['calories'])],
                    [__('calories365-bot.proteins', [], $locale), round($p['proteins'])],
                    [__('calories365-bot.fats', [], $locale), round($p['fats'])],
                    [__('calories365-bot.carbohydrates', [], $locale), round($p['carbohydrates'])],
                ];
                $text .= ($useBigFont
                    ? \App\Utilities\Utilities::generateTableType2ForBigFont($p['name'], $table)
                    : \App\Utilities\Utilities::generateTableType2($p['name'], $table)
                )."\n\n";
            }

            $table = [
                [__('calories365-bot.calories', [], $locale), round($total['calories'])],
                [__('calories365-bot.proteins', [], $locale), round($total['proteins'])],
                [__('calories365-bot.fats', [], $locale), round($total['fats'])],
                [__('calories365-bot.carbohydrates', [], $locale), round($total['carbohydrates'])],
            ];
            $text .= ($useBigFont
                ? \App\Utilities\Utilities::generateTableType2ForBigFont(__('calories365-bot.total_for_day', [], $locale), $table)
                : \App\Utilities\Utilities::generateTableType2(__('calories365-bot.total_for_day', [], $locale), $table)
            );

            return $text;
        }
    }
}
