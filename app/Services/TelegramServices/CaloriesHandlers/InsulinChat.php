<?php

namespace App\Services\TelegramServices\CaloriesHandlers;

use App\Services\DiaryApiService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\FileUpload\InputFile;

/**
 * Blood sugar and insulin of a day in the chat, as the diary estimates them: the
 * diary draws both curves, the bot sends them as a photo with what each meal
 * did and what movement and sleep changed, and buttons for the day before, to
 * log movement, the way of life and the full page in the Mini App; then the
 * totals of the day that matter, protein, fibre, glycemic load, and what to
 * change, as a second message.
 */
class InsulinChat
{
    /**
     * Telegram refuses a caption over 1024 characters.
     */
    private const CAPTION_LIMIT = 1000;

    private const FOODS_PER_MEAL = 2;

    protected DiaryApiService $diaryApiService;

    public function __construct(DiaryApiService $diaryApiService)
    {
        $this->diaryApiService = $diaryApiService;
    }

    public function send($bot, $telegram, $botUser, int $chatId, ?string $date = null): void
    {
        $date ??= date('Y-m-d');
        $telegram->sendChatAction(['chat_id' => $chatId, 'action' => 'upload_photo']);

        $day = $this->diaryApiService->insulinDay($date, $botUser->calories_id, $botUser->locale);
        $chart = isset($day['error']) ? false : base64_decode((string) ($day['chart'] ?? ''), true);

        if (! $chart) {
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => __('calories365-bot.draft_failed')]);

            return;
        }

        try {
            $telegram->sendPhoto([
                'chat_id' => $chatId,
                'photo' => InputFile::createFromContents($chart, 'insulin-'.$date.'.png'),
                'caption' => $this->caption($day),
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => $this->keyboard($day, $bot->web_app_url ?? null)]),
            ]);
        } catch (\Throwable $e) {
            Log::error('Cannot send the insulin chart', ['error' => $e->getMessage()]);
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => __('calories365-bot.draft_failed')]);

            return;
        }

        // The totals say something only about a day with food.
        if ($day['meals'] !== []) {
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => $this->summary($day), 'parse_mode' => 'HTML']);
        }
    }

    /**
     * The totals of the day and what to change, in plain lines of the user's language.
     *
     * @param  array<string, mixed>  $summary  InsulinDay summary of the diary
     * @param  array<string, mixed>  $glucose  its sugar: peak, limits
     * @return array<int, string>
     */
    public static function summaryLines(array $summary, array $glucose): array
    {
        $lines = [];
        $lines[] = $summary['proteins_target'] !== null
            ? __('calories365-bot.insulin_summary_proteins_kg', ['grams' => round($summary['proteins']), 'per_kg' => self::number($summary['proteins_per_kg']), 'target' => $summary['proteins_target']])
            : __('calories365-bot.insulin_summary_proteins', ['grams' => round($summary['proteins'])]);
        $lines[] = __('calories365-bot.insulin_summary_fibers', ['grams' => round($summary['fibers']), 'target' => $summary['fibers_target']])
            .(($summary['fibers_unknown'] ?? 0) > 0 ? __('calories365-bot.insulin_summary_fibers_unknown', ['count' => $summary['fibers_unknown']]) : '');
        $lines[] = __('calories365-bot.insulin_summary_sugars', ['grams' => round($summary['sugars'])]);
        $lines[] = __('calories365-bot.insulin_summary_load', ['load' => round($summary['glycemic_load']), 'level' => __('calories365-bot.insulin_load_'.$summary['glycemic_load_level'])]);
        $lines[] = __('calories365-bot.insulin_summary_sugar', ['level' => self::number($glucose['peak']['level']), 'time' => self::time($glucose['peak']['minute']), 'minutes' => $summary['glucose_high_minutes']]);
        if ($summary['ultra_processed_share'] !== null) {
            $lines[] = __('calories365-bot.insulin_summary_ultra', ['percent' => $summary['ultra_processed_share']]);
        }
        $lines[] = __('calories365-bot.insulin_summary_moved', ['minutes' => $summary['moved_minutes'], 'after' => $summary['moved_after_meals_minutes']])
            .($summary['sleep_hours'] !== null ? ' · '.__('calories365-bot.insulin_summary_sleep', ['hours' => self::number($summary['sleep_hours'])]) : '');

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $day
     */
    private function summary(array $day): string
    {
        $text = '<b>'.e(__('calories365-bot.insulin_summary_title')).'</b>'."\n"
            .implode("\n", array_map('e', self::summaryLines($day['summary'], $day['glucose'])));

        if ($day['tips'] !== []) {
            $text .= "\n\n".'<b>'.e(__('calories365-bot.insulin_tips')).'</b>'."\n"
                .implode("\n", array_map(static fn (string $tip) => '• '.e($tip), $day['tips']));
        }

        return $text;
    }

    /**
     * The five ways of life to choose from, the current one marked.
     */
    public function askLifestyle($telegram, $botUser, int $chatId, string $date): void
    {
        $day = $this->diaryApiService->insulinDay($date, $botUser->calories_id, $botUser->locale, false);
        $current = (int) ($day['lifestyle'] ?? 0);

        $rows = [];
        foreach (range(1, 5) as $level) {
            $rows[] = [[
                'text' => ($level === $current ? '✓ ' : '').__('calories365-bot.lifestyle_'.$level),
                'callback_data' => "insulin_set_{$level}_{$date}",
            ]];
        }

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => __('calories365-bot.lifestyle_question'),
            'reply_markup' => json_encode(['inline_keyboard' => $rows]),
        ]);
    }

    /**
     * @return bool whether the diary kept it
     */
    public function setLifestyle($botUser, int $level, string $date): bool
    {
        return ! isset($this->diaryApiService->setLifestyle($level, $date, $botUser->calories_id, $botUser->locale)['error']);
    }

    /**
     * @param  array<string, mixed>  $day
     */
    private function caption(array $day): string
    {
        $zones = $day['zones'];
        $glucose = $day['glucose'];
        $level = fn (array $point) => __('calories365-bot.insulin_level', [
            'level' => round($point['level']),
            'zone' => __('calories365-bot.insulin_zone_'.$this->zone($point['level'], $zones)),
        ]);
        $eaten = $day['meals'] !== [];

        $sugar = array_filter([
            $day['now'] !== null ? (string) __('calories365-bot.insulin_now', ['level' => self::number($day['now']['glucose'])]) : null,
            $eaten ? (string) __('calories365-bot.insulin_peak', ['level' => self::number($glucose['peak']['level']), 'time' => self::time($glucose['peak']['minute'])]) : null,
        ]);
        $insulin = array_filter([
            $day['now'] !== null ? (string) __('calories365-bot.insulin_now', ['level' => $level($day['now'])]) : null,
            $eaten ? (string) __('calories365-bot.insulin_peak', ['level' => $level($day['peak']), 'time' => self::time($day['peak']['minute'])]) : null,
        ]);

        $head = ['<b>'.e(__('calories365-bot.insulin_title', ['date' => Carbon::parse($day['date'])->format('d.m')])).'</b>', e(__('calories365-bot.insulin_note'))];
        if ($sugar !== []) {
            $head[] = e(__('calories365-bot.insulin_sugar_line', ['parts' => implode(', ', $sugar)]));
        }
        if ($insulin !== []) {
            $head[] = e(__('calories365-bot.insulin_insulin_line', ['parts' => implode(', ', $insulin)]));
        }

        $meals = [];
        foreach ($day['meals'] as $meal) {
            $foods = array_map(
                static fn (array $item) => e(mb_strimwidth($item['name'], 0, 28, '…')).' +'.self::number($item['glucose']),
                array_slice(array_filter($meal['items'], static fn (array $item) => $item['glucose'] >= 0.1), 0, self::FOODS_PER_MEAL)
            );
            $walk = collect($meal['factors'])->firstWhere('key', 'walk');
            $meals[] = '<b>'.e(self::time($meal['minute']).' '.__('calories365-bot.insulin_part_'.$meal['part_of_day'])).'</b> '
                .e(__('calories365-bot.insulin_meal', ['sugar' => self::number($meal['glucose_peak']), 'insulin' => round($meal['rise'])]))
                .($walk !== null ? e(' ('.$walk['label'].')') : '')
                .($foods === [] ? '' : ': '.implode(', ', $foods));
        }

        $moves = array_map(fn (array $activity) => e(trim(($activity['emoji'] ?? '').' '.__('calories365-bot.insulin_activity', [
            'time' => self::time($activity['minute']),
            'title' => mb_strimwidth($activity['title'], 0, 40, '…'),
            'duration' => $this->duration($activity['minutes']),
        ])).$this->effect($activity)), $day['activities']);

        $foot = [e(__('calories365-bot.insulin_lifestyle', ['lifestyle' => mb_strtolower(__('calories365-bot.lifestyle_'.$day['lifestyle']))]))];
        if (! $day['lifestyle_known']) {
            $foot[] = e(__('calories365-bot.insulin_lifestyle_hint'));
        }
        if ($moves === []) {
            $foot[] = e(__('calories365-bot.insulin_movement_hint'));
        }

        $sections = [implode("\n", $head)];
        $sections[] = $meals === []
            ? e(__('calories365-bot.insulin_no_meals'))
            : '<b>'.e(__('calories365-bot.insulin_meals')).'</b>'."\n".implode("\n", $meals);
        if ($moves !== []) {
            $sections[] = '<b>'.e(__('calories365-bot.insulin_moves')).'</b>'."\n".implode("\n", $moves);
        }
        $sections[] = implode("\n", $foot);

        // A long day keeps its summary: the lines of foods and moves go first.
        while (mb_strlen(strip_tags(implode("\n\n", $sections))) > self::CAPTION_LIMIT && count($sections) > 2) {
            array_splice($sections, 1, 1);
        }

        return implode("\n\n", $sections);
    }

    /**
     * " — сахар −4%, инсулин −5%", or nothing for what changed nothing.
     *
     * @param  array<string, mixed>  $activity
     */
    private function effect(array $activity): string
    {
        if ($activity['glucose_effect'] === 0 && $activity['effect'] === 0) {
            return '';
        }

        $more = $activity['glucose_effect'] < 0;

        return ' — '.__($more ? 'calories365-bot.insulin_effect_more' : 'calories365-bot.insulin_effect', [
            'glucose' => abs($activity['glucose_effect']),
            'insulin' => abs($activity['effect']),
        ]);
    }

    private function duration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($hours === 0) {
            return __('calories365-bot.move_minutes', ['minutes' => $minutes]);
        }

        return trim(__('calories365-bot.move_hours', ['hours' => $hours]).($rest > 0 ? ' '.__('calories365-bot.move_minutes', ['minutes' => $rest]) : ''));
    }

    /**
     * @param  array<string, mixed>  $day
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function keyboard(array $day, ?string $miniAppUrl): array
    {
        $date = Carbon::parse($day['date']);
        $days = [['text' => '◀ '.$date->copy()->subDay()->format('d.m'), 'callback_data' => 'insulin_day_'.$date->copy()->subDay()->toDateString()]];
        if ($date->lt(today())) {
            $days[] = ['text' => $date->copy()->addDay()->format('d.m').' ▶', 'callback_data' => 'insulin_day_'.$date->copy()->addDay()->toDateString()];
        }

        $rows = [$days, [
            ['text' => __('calories365-bot.insulin_move_button'), 'callback_data' => 'move_ask'],
            ['text' => __('calories365-bot.lifestyle_button'), 'callback_data' => 'insulin_lifestyle_'.$date->toDateString()],
        ]];

        if ($miniAppUrl) {
            $rows[] = [['text' => __('calories365-bot.insulin_open'), 'web_app' => ['url' => rtrim($miniAppUrl, '/').'/insulin']]];
        }

        return $rows;
    }

    /**
     * @param  array{medium: float, high: float}  $zones
     */
    private function zone(float $level, array $zones): string
    {
        return $level >= $zones['high'] ? 'high' : ($level >= $zones['medium'] ? 'medium' : 'low');
    }

    private static function time(int $minute): string
    {
        $inDay = (($minute % 1440) + 1440) % 1440;

        return sprintf('%02d:%02d', intdiv($inDay, 60), $inDay % 60);
    }

    /**
     * 6,1 in Russian and Ukrainian, 6.1 in English.
     */
    private static function number(float $value): string
    {
        $number = (string) round($value, 1);

        return app()->getLocale() === 'en' ? $number : str_replace('.', ',', $number);
    }
}
