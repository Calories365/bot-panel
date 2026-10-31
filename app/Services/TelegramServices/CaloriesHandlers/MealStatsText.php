<?php

namespace App\Services\TelegramServices\CaloriesHandlers;

use App\Utilities\Utilities;

/**
 * What the statistics of the calories bot say about the meals of a day, in the
 * legacy Markdown of Telegram: product names are escaped, since a name like
 * "Lay`s" would otherwise make Telegram refuse the whole message.
 */
class MealStatsText
{
    /**
     * Telegram refuses a message over 4096 characters; the products of a very
     * long day go first, the totals stay.
     */
    private const MESSAGE_LIMIT = 4000;

    /**
     * "Хлеб (80г) — обработанный"
     *
     * @param  array<string, mixed>  $meal  a meal of the diary's showUserStats
     */
    public static function productTitle(array $meal, string $locale): string
    {
        return Utilities::escapeMarkdown((string) $meal['name'])." ({$meal['quantity']}г)".self::processing($meal, $locale);
    }

    /**
     * The day by parts, with what was eaten in each, and its total.
     *
     * @param  array<int, array<string, mixed>>  $meals
     */
    public static function allDay(array $meals, string $date, string $locale, bool $useBigFont): string
    {
        $text = self::dayText($meals, $date, $locale, $useBigFont, true);

        return mb_strlen($text) > self::MESSAGE_LIMIT ? self::dayText($meals, $date, $locale, $useBigFont, false) : $text;
    }

    /**
     * @param  array<int, array<string, mixed>>  $meals
     */
    private static function dayText(array $meals, string $date, string $locale, bool $useBigFont, bool $withProducts): string
    {
        $names = ['morning' => 'breakfast', 'dinner' => 'lunch', 'supper' => 'dinner'];
        $total = self::zero();
        $text = __('calories365-bot.stats_day_title', ['date' => $date], $locale)."\n\n";

        foreach ($names as $part => $name) {
            $partMeals = array_filter($meals, static fn (array $meal) => $meal['part_of_day'] === $part);

            if ($partMeals === []) {
                continue;
            }

            $sum = self::zero();
            foreach ($partMeals as $meal) {
                $factor = $meal['quantity'] / 100;
                foreach (array_keys($sum) as $fact) {
                    $sum[$fact] += $meal[$fact] * $factor;
                    $total[$fact] += $meal[$fact] * $factor;
                }
            }

            $text .= self::table(__('calories365-bot.'.$name, [], $locale), $sum, $locale, $useBigFont)."\n";

            if ($withProducts) {
                foreach ($partMeals as $meal) {
                    $text .= '• '.self::productTitle($meal, $locale)."\n";
                }
            }

            $text .= "\n";
        }

        return $text.self::table(__('calories365-bot.total_for_day', [], $locale), $total, $locale, $useBigFont);
    }

    /**
     * @param  array<string, float>  $values
     */
    private static function table(string $title, array $values, string $locale, bool $useBigFont): string
    {
        $rows = [];
        foreach ($values as $fact => $value) {
            $rows[] = [__('calories365-bot.'.$fact, [], $locale), round($value)];
        }

        return $useBigFont
            ? Utilities::generateTableType2ForBigFont($title, $rows)
            : Utilities::generateTableType2($title, $rows);
    }

    /**
     * @param  array<string, mixed>  $meal
     */
    private static function processing(array $meal, string $locale): string
    {
        $nova = $meal['nova'] ?? null;

        return in_array($nova, [1, 2, 3, 4], true) ? ' — '.__('calories365-bot.processing_'.$nova, [], $locale) : '';
    }

    /**
     * @return array{calories: float, proteins: float, fats: float, carbohydrates: float}
     */
    private static function zero(): array
    {
        return ['calories' => 0.0, 'proteins' => 0.0, 'fats' => 0.0, 'carbohydrates' => 0.0];
    }
}
