<?php

namespace App\Services\TelegramServices\CaloriesHandlers;

use App\Services\DiaryApiService;
use App\Services\VoiceRateLimiter;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The meal draft in the chat.
 *
 * Voice messages, photos of dishes and of labels, and texts are kept in the
 * diary (collect) until the user presses "Посчитать": the diary then reads all
 * of them together with the draft in one model request, in its queue, and the
 * bot waits for the outcome (calculate). A short edit, "2 250", changes the
 * draft at once (input). The bot keeps one message at the bottom of the chat
 * showing the draft and what waits for the calculation: every answer sends it
 * anew and deletes the one before, with the buttons to calculate, save the meal
 * or drop it.
 */
class MealDraftChat
{
    protected DiaryApiService $diaryApiService;

    protected VoiceRateLimiter $voiceRateLimiter;

    public function __construct(DiaryApiService $diaryApiService, VoiceRateLimiter $voiceRateLimiter)
    {
        $this->diaryApiService = $diaryApiService;
        $this->voiceRateLimiter = $voiceRateLimiter;
    }

    /**
     * Count one model call for the spam guard, shared with the site; false,
     * with a message to the user, when the hour is used up.
     */
    public function allowed($telegram, $botUser, int $chatId, string $limitMessage = 'draft_rate_limited'): bool
    {
        $caloriesId = (int) $botUser->calories_id;

        if ($this->voiceRateLimiter->attempt($caloriesId)) {
            return true;
        }

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => __('calories365-bot.'.$limitMessage, [
                'minutes' => $this->voiceRateLimiter->minutesUntilAvailable($caloriesId),
            ]),
        ]);

        return false;
    }

    /**
     * Send a short edit of the draft, "2 250" or "убрать 3", which the diary
     * applies at once, and show what the draft became.
     */
    public function input($bot, $telegram, $botUser, int $chatId, int $userId, string $text): void
    {
        $this->oneAtATime($telegram, $chatId, $userId, function () use ($bot, $telegram, $botUser, $chatId, $userId, $text) {
            $response = $this->diaryApiService->draftInput($text, null, $botUser->calories_id, $botUser->locale);

            if (isset($response['error'])) {
                $telegram->sendMessage(['chat_id' => $chatId, 'text' => __('calories365-bot.draft_failed')]);

                return;
            }

            $this->show($bot, $telegram, $chatId, $userId, $response['draft'] ?? null, $response['inputs'] ?? [], $response['not_found'] ?? []);
        });
    }

    /**
     * Keep a voice message (its words), a text or a photo with its caption for
     * the calculation, and show what waits.
     *
     * @param  array{contents: string, name: string}|null  $photo
     */
    public function collect($bot, $telegram, $botUser, int $chatId, int $userId, string $kind, ?string $text, ?array $photo = null): void
    {
        $this->oneAtATime($telegram, $chatId, $userId, function () use ($bot, $telegram, $botUser, $chatId, $userId, $kind, $text, $photo) {
            $response = $this->diaryApiService->draftCollect($kind, $text, $photo, $botUser->calories_id, $botUser->locale);

            if (isset($response['error'])) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => __(($response['message'] ?? null) === 'too_many_inputs' ? 'calories365-bot.draft_too_many' : 'calories365-bot.draft_failed'),
                ]);

                return;
            }

            $this->show($bot, $telegram, $chatId, $userId, $response['draft'] ?? null, $response['inputs'] ?? [], $response['not_found'] ?? []);
        });
    }

    /**
     * Calculate the draft from what waits and show it. The diary calculates in
     * its queue; the bot asks until it is done.
     */
    public function calculate($bot, $telegram, $botUser, int $chatId, int $userId): void
    {
        $telegram->sendChatAction(['chat_id' => $chatId, 'action' => 'typing']);

        $state = $this->diaryApiService->draftCalculate($botUser->calories_id, $botUser->locale);

        if (isset($state['error'])) {
            // Pressed again after the bot stopped waiting: the calculation took
            // what waited and its draft is ready.
            $shown = ($state['message'] ?? null) === 'nothing_to_calculate'
                ? $this->diaryApiService->draftShow($botUser->calories_id, $botUser->locale)
                : [];

            if (($shown['draft'] ?? null) !== null) {
                $this->show($bot, $telegram, $chatId, $userId, $shown['draft'], $shown['inputs'] ?? [], $shown['calculation']['not_found'] ?? []);

                return;
            }

            $nothing = ($state['message'] ?? null) === 'nothing_to_calculate';
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => __($nothing ? 'calories365-bot.draft_nothing_to_calculate' : 'calories365-bot.draft_failed')]);

            return;
        }

        $poll = (int) config('services.diary_api.calculation_poll_seconds');
        $deadline = time() + (int) config('services.diary_api.calculation_wait_seconds');

        while (($state['calculation']['status'] ?? null) === 'running' && time() < $deadline) {
            sleep($poll);
            // "Typing…" lasts five seconds in Telegram.
            $telegram->sendChatAction(['chat_id' => $chatId, 'action' => 'typing']);

            $next = $this->diaryApiService->draftShow($botUser->calories_id, $botUser->locale);
            if (! isset($next['error'])) {
                $state = $next;
            }
        }

        $calculation = $state['calculation'] ?? [];
        $notice = match ($calculation['status'] ?? null) {
            'running' => 'draft_still_calculating',
            'failed' => 'draft_not_read',
            'done' => ($calculation['understood'] ?? false) ? null : 'draft_not_understood',
            default => null,
        };

        if ($notice !== null) {
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => __('calories365-bot.'.$notice)]);
        }

        if (($calculation['status'] ?? null) !== 'running') {
            $this->show($bot, $telegram, $chatId, $userId, $state['draft'] ?? null, $state['inputs'] ?? [], $calculation['not_found'] ?? []);
        }
    }

    /**
     * Replace the draft message with one showing the draft and what waits for
     * the calculation; with neither only the old message goes.
     *
     * @param  array<string, mixed>|null  $draft  MealDraft::toPayload of the diary
     * @param  array<int, array{id: int, kind: string, text: string|null}>  $inputs  what waits for the calculation
     * @param  array<int, string>  $notFound  foods the diary found no product for
     */
    public function show($bot, $telegram, int $chatId, int $userId, ?array $draft, array $inputs = [], array $notFound = []): void
    {
        if ($draft === null && $inputs === []) {
            $this->forget($telegram, $chatId, $userId);

            return;
        }

        $previous = Cache::get("meal_draft_message_{$userId}");

        $sent = $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $this->text($draft, $inputs, $notFound),
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $this->keyboard($draft !== null, $inputs !== [], $bot->web_app_url ?? null)]),
        ]);

        Cache::put("meal_draft_message_{$userId}", $sent->getMessageId(), now()->addHours(6));

        if ($previous) {
            $this->deleteMessage($telegram, $chatId, (int) $previous);
        }
    }

    /**
     * Delete the draft message: the meal was saved or dropped.
     */
    public function forget($telegram, int $chatId, int $userId): void
    {
        $previous = Cache::pull("meal_draft_message_{$userId}");

        if ($previous) {
            $this->deleteMessage($telegram, $chatId, (int) $previous);
        }
    }

    /**
     * The photos of an album come as separate updates; one at a time keeps the
     * draft message in step with what the diary holds.
     */
    private function oneAtATime($telegram, int $chatId, int $userId, callable $work): void
    {
        $telegram->sendChatAction(['chat_id' => $chatId, 'action' => 'typing']);

        try {
            Cache::lock("meal_draft_input_{$userId}", 60)->block(50, $work);
        } catch (LockTimeoutException) {
            Log::warning('Meal draft input waited too long for the previous one', ['user' => $userId]);
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => __('calories365-bot.draft_failed')]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $draft
     * @param  array<int, array{id: int, kind: string, text: string|null}>  $inputs
     * @param  array<int, string>  $notFound
     */
    private function text(?array $draft, array $inputs, array $notFound): string
    {
        $lines = ['<b>'.e(__('calories365-bot.draft_title')).'</b>'];

        foreach ($draft['items'] ?? [] as $item) {
            $mark = match (true) {
                (bool) $item['edited'] => ' — '.__('calories365-bot.draft_edited'),
                $item['source'] === 'label' => ' — '.__('calories365-bot.draft_label'),
                default => '',
            };

            $lines[] = __('calories365-bot.draft_item', [
                'number' => $item['number'],
                'name' => e($item['name']),
                'grams' => $item['grams'],
                'calories' => round($item['portion']['calories']),
            ]).$mark;
        }

        if ($draft !== null) {
            $total = $draft['total'];
            $lines[] = '';
            $lines[] = '<b>'.e(__('calories365-bot.draft_total', ['calories' => round($total['calories'])])).'</b> · '
                .e(__('calories365-bot.draft_macros', [
                    'proteins' => round($total['proteins']),
                    'fats' => round($total['fats']),
                    'carbohydrates' => round($total['carbohydrates']),
                ]));
        }

        if ($notFound !== []) {
            $lines[] = '';
            $lines[] = e(__('calories365-bot.draft_not_found', ['names' => implode(', ', $notFound)]));
        }

        if ($inputs !== []) {
            $lines[] = '';
            $lines[] = '<b>'.e(__($draft === null ? 'calories365-bot.draft_inputs' : 'calories365-bot.draft_inputs_more')).'</b>';

            foreach ($inputs as $input) {
                $kind = __('calories365-bot.draft_input_'.$input['kind']);
                $lines[] = '• '.e($input['text'] !== null && $input['text'] !== '' ? $kind.': «'.mb_strimwidth($input['text'], 0, 200, '…').'»' : $kind);
            }
        }

        $lines[] = '';
        $lines[] = e(__($inputs !== [] ? 'calories365-bot.draft_inputs_hint' : 'calories365-bot.draft_hint'));

        return implode("\n", $lines);
    }

    /**
     * Calculate when something waits; breakfast, lunch and dinner, the one of
     * the time of day first, when there is a draft; edit, which opens the draft
     * on the "Приём пищи" page of the Mini App, and cancel.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function keyboard(bool $hasDraft, bool $hasInputs, ?string $miniAppUrl): array
    {
        $rows = [];

        if ($hasInputs) {
            $rows[] = [['text' => __($hasDraft ? 'calories365-bot.draft_recalculate' : 'calories365-bot.draft_calculate'), 'callback_data' => 'draft_calculate']];
        }

        if ($hasDraft) {
            $meals = ['morning' => 'breakfast', 'dinner' => 'lunch', 'supper' => 'dinner'];
            $hour = (int) now()->format('G');
            $now = $hour < 11 ? 'morning' : ($hour < 16 ? 'dinner' : 'supper');
            $meals = [$now => $meals[$now]] + $meals;

            $row = [];
            foreach ($meals as $partOfDay => $label) {
                $row[] = ['text' => __('calories365-bot.'.$label), 'callback_data' => 'draft_save_'.$partOfDay];
            }
            $rows[] = $row;
        }

        $actions = [['text' => __('calories365-bot.cancel'), 'callback_data' => 'draft_cancel']];
        if ($miniAppUrl) {
            array_unshift($actions, ['text' => __('calories365-bot.draft_edit'), 'web_app' => ['url' => rtrim($miniAppUrl, '/').'/voice']]);
        }
        $rows[] = $actions;

        return $rows;
    }

    private function deleteMessage($telegram, int $chatId, int $messageId): void
    {
        try {
            $telegram->deleteMessage(['chat_id' => $chatId, 'message_id' => $messageId]);
        } catch (\Throwable $e) {
            // The user may have deleted it, or it is older than Telegram lets a bot delete.
            Log::info('Could not delete the old meal draft message', ['error' => $e->getMessage()]);
        }
    }
}
