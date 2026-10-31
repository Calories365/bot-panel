<?php

namespace App\Services\TelegramServices\CaloriesHandlers;

use App\Services\DiaryApiService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\FileUpload\InputFile;

/**
 * The estimated insulin of a day in the chat: the diary draws the curve, the bot
 * sends it as a photo with what lifted it and what lowered it, and buttons for
 * the day before, the way of life and the full page in the Mini App.
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
        }
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
        $level = fn (array $point) => __('calories365-bot.insulin_level', [
            'level' => round($point['level']),
            'zone' => __('calories365-bot.insulin_zone_'.$this->zone($point['level'], $zones)),
        ]);

        $head = ['<b>'.e(__('calories365-bot.insulin_title', ['date' => Carbon::parse($day['date'])->format('d.m')])).'</b>', e(__('calories365-bot.insulin_note'))];
        $levels = array_filter([
            $day['now'] !== null ? __('calories365-bot.insulin_now', ['level' => $level($day['now'])]) : null,
            // A day without food has no peak to speak of.
            $day['peak']['level'] > $day['base'] + 1 ? __('calories365-bot.insulin_peak', ['level' => $level($day['peak']), 'time' => $this->time($day['peak']['minute'])]) : null,
        ]);
        if ($levels !== []) {
            $head[] = e(implode(' · ', $levels));
        }

        $meals = [];
        foreach ($day['meals'] as $meal) {
            $foods = array_map(
                fn (array $item) => e(mb_strimwidth($item['name'], 0, 32, '…')).' +'.round($item['rise']),
                array_slice($meal['items'], 0, self::FOODS_PER_MEAL)
            );
            $meals[] = e($this->time($meal['minute']).' '.__('calories365-bot.insulin_part_'.$meal['part_of_day'])).' <b>+'.round($meal['rise']).'</b>'
                .($foods === [] ? '' : ': '.implode(', ', $foods));
        }

        $moves = array_map(fn (array $activity) => e(__('calories365-bot.insulin_activity', [
            'time' => $this->time($activity['minute']),
            'title' => mb_strimwidth($activity['title'], 0, 40, '…'),
            'minutes' => $activity['minutes'],
        ]).($activity['effect'] > 0 ? ' — '.__('calories365-bot.insulin_effect', ['percent' => $activity['effect']]) : '')), $day['activities']);

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

        $rows = [$days, [['text' => __('calories365-bot.lifestyle_button'), 'callback_data' => 'insulin_lifestyle_'.$date->toDateString()]]];

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

    private function time(int $minute): string
    {
        $inDay = (($minute % 1440) + 1440) % 1440;

        return sprintf('%02d:%02d', intdiv($inDay, 60), $inDay % 60);
    }
}
