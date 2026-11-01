<?php

namespace App\Services\TelegramServices\CaloriesHandlers;

use App\Services\DiaryApiService;
use Illuminate\Support\Facades\Cache;

/**
 * Movement and sleep in the chat, as quick to tell as a meal.
 *
 * "🏃 Движение" asks what the user did and shows the kinds of the diary's
 * catalogue as buttons: a kind, then its minutes, and the activity is kept at
 * once, ended now. For the next minutes a voice message or a text is read as
 * movement and kept at once too, without the meal draft and "Посчитать":
 * "час гулял", "после обеда сходил в магазин", "спал с часу до семи"; one that
 * tells no movement goes to the meal draft as usual. Each kept activity comes
 * with a button to remove it and one for the chart of the day.
 */
class MovementChat
{
    /**
     * How long after "🏃 Движение" a message is read as movement.
     */
    private const WAITS_MINUTES = 10;

    /**
     * The most common kinds come first; the rest wait behind "more".
     */
    private const SHOWN_FIRST = 8;

    private const MINUTES = [10, 15, 20, 30, 45, 60, 90, 120];

    private const SLEEP_MINUTES = [300, 360, 420, 480, 540];

    protected DiaryApiService $diaryApiService;

    public function __construct(DiaryApiService $diaryApiService)
    {
        $this->diaryApiService = $diaryApiService;
    }

    /**
     * Ask what the user did, with the kinds as buttons; the next message is
     * read as movement.
     */
    public function ask($telegram, $botUser, int $chatId, int $userId, bool $all = false): void
    {
        Cache::put(self::waitingKey($userId), true, now()->addMinutes(self::WAITS_MINUTES));

        $types = $this->diaryApiService->activityTypes($botUser->locale ?? 'ru', $botUser->calories_id);
        $shown = $all ? $types : array_slice($types, 0, self::SHOWN_FIRST);

        $buttons = array_map(static fn (array $type) => [
            'text' => $type['emoji'].' '.$type['title'],
            'callback_data' => 'move_type_'.$type['type'],
        ], $shown);
        $rows = array_chunk($buttons, 2);

        if (! $all && count($types) > count($shown)) {
            $rows[] = [['text' => __('calories365-bot.move_more'), 'callback_data' => 'move_more']];
        }

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => __('calories365-bot.move_question'),
            'reply_markup' => json_encode(['inline_keyboard' => $rows]),
        ]);
    }

    /**
     * How long, for a kind picked.
     */
    public function askMinutes($telegram, $botUser, int $chatId, string $type): void
    {
        $known = collect($this->diaryApiService->activityTypes($botUser->locale ?? 'ru', $botUser->calories_id))->firstWhere('type', $type);

        if ($known === null) {
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => __('calories365-bot.draft_failed')]);

            return;
        }

        $buttons = array_map(fn (int $minutes) => [
            'text' => $this->duration($minutes),
            'callback_data' => "move_add_{$type}_{$minutes}",
        ], $type === 'sleep' ? self::SLEEP_MINUTES : self::MINUTES);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => __($type === 'sleep' ? 'calories365-bot.move_how_long_sleep' : 'calories365-bot.move_how_long', ['title' => $known['emoji'].' '.$known['title']]),
            'reply_markup' => json_encode(['inline_keyboard' => array_chunk($buttons, 4)]),
        ]);
    }

    /**
     * Keep an activity of a kind for so many minutes, ended now.
     */
    public function add($telegram, $botUser, int $chatId, int $userId, string $type, int $minutes): void
    {
        Cache::forget(self::waitingKey($userId));
        $response = $this->diaryApiService->addActivity($type, $minutes, date('Y-m-d'), $botUser->calories_id, $botUser->locale ?? 'ru');

        if (isset($response['error']) || ! isset($response['activity'])) {
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => __('calories365-bot.draft_failed')]);

            return;
        }

        $this->tell($telegram, $chatId, [$response['activity']]);
    }

    /**
     * Keep what a message says the user did. False when it tells no movement:
     * the caller passes it on to the meal draft, since it was most likely food.
     */
    public function say($telegram, $botUser, int $chatId, int $userId, string $text): bool
    {
        Cache::forget(self::waitingKey($userId));
        $telegram->sendChatAction(['chat_id' => $chatId, 'action' => 'typing']);

        $response = $this->diaryApiService->sayActivity($text, $botUser->calories_id, $botUser->locale ?? 'ru');

        if (isset($response['error'])) {
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => __('calories365-bot.draft_failed')]);

            return true;
        }

        if (! ($response['understood'] ?? false)) {
            return false;
        }

        $this->tell($telegram, $chatId, $response['recorded']);

        return true;
    }

    public function remove($telegram, $botUser, int $chatId, int $messageId, int $id): void
    {
        $removed = $this->diaryApiService->deleteActivity($id, $botUser->calories_id, $botUser->locale ?? 'ru');

        $telegram->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => __($removed ? 'calories365-bot.move_removed' : 'calories365-bot.draft_failed'),
        ]);
    }

    /**
     * Whether the user pressed "🏃 Движение" a moment ago and the next message
     * is about movement.
     */
    public static function waiting(int $userId): bool
    {
        return (bool) Cache::get(self::waitingKey($userId));
    }

    /**
     * Say what was kept, with a button to remove each and one for the chart.
     *
     * @param  array<int, array<string, mixed>>  $activities  Activity::toPayload of the diary
     */
    private function tell($telegram, int $chatId, array $activities): void
    {
        $lines = [];
        $rows = [];

        foreach ($activities as $activity) {
            $lines[] = __('calories365-bot.move_recorded', [
                'title' => $activity['title'],
                'duration' => $this->duration((int) $activity['minutes']),
                'time' => $this->span($activity),
                'calories' => $activity['calories'],
            ]);
            $rows[] = [['text' => __('calories365-bot.move_remove', ['title' => mb_strimwidth($activity['title'], 0, 30, '…')]), 'callback_data' => 'move_del_'.$activity['id']]];
        }

        $rows[] = [['text' => __('calories365-bot.insulin_chart'), 'callback_data' => 'insulin_day_'.date('Y-m-d')]];

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => implode("\n", $lines),
            'reply_markup' => json_encode(['inline_keyboard' => $rows]),
        ]);
    }

    /**
     * " (18:30–19:00)", or "" without a time.
     *
     * @param  array<string, mixed>  $activity
     */
    private function span(array $activity): string
    {
        if (empty($activity['time'])) {
            return '';
        }

        [$hours, $minutes] = array_map('intval', explode(':', $activity['time']));
        $end = ($hours * 60 + $minutes + (int) $activity['minutes']) % 1440;

        return ' ('.$activity['time'].'–'.sprintf('%02d:%02d', intdiv($end, 60), $end % 60).')';
    }

    private function duration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($hours === 0) {
            return __('calories365-bot.move_minutes', ['minutes' => $minutes]);
        }

        return $rest === 0
            ? __('calories365-bot.move_hours', ['hours' => $hours])
            : __('calories365-bot.move_hours', ['hours' => $hours]).' '.__('calories365-bot.move_minutes', ['minutes' => $rest]);
    }

    private static function waitingKey(int $userId): string
    {
        return "movement_waiting_{$userId}";
    }
}
