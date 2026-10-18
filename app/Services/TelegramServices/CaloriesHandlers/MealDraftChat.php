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
 * A voice message, a photo or a text goes to the user's meal draft in the diary,
 * which reads it and answers with the whole draft. The bot keeps one message
 * showing the draft at the bottom of the chat: every answer sends it anew and
 * deletes the one before, with the buttons to save the meal or drop it.
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
     * Count one input for the spam guard, shared with voice input on the site;
     * false, with a message to the user, when the hour is used up.
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
     * Send a text or a photo to the draft and show what it became.
     *
     * @param  array{contents: string, name: string}|null  $photo
     */
    public function input($bot, $telegram, $botUser, int $chatId, int $userId, ?string $text, ?array $photo = null): void
    {
        $telegram->sendChatAction(['chat_id' => $chatId, 'action' => 'typing']);

        try {
            // The photos of an album come as separate updates; one input at a time
            // keeps the item numbers the model sees in line with the draft.
            Cache::lock("meal_draft_input_{$userId}", 120)->block(100, function () use ($bot, $telegram, $botUser, $chatId, $userId, $text, $photo) {
                $response = $this->diaryApiService->draftInput($text, $photo, $botUser->calories_id, $botUser->locale);

                if (isset($response['error'])) {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => __(($response['status'] ?? 0) === 503 ? 'calories365-bot.draft_not_read' : 'calories365-bot.draft_failed'),
                    ]);

                    return;
                }

                if (! ($response['understood'] ?? false)) {
                    $telegram->sendMessage(['chat_id' => $chatId, 'text' => __('calories365-bot.draft_not_understood')]);

                    return;
                }

                $this->show($bot, $telegram, $chatId, $userId, $response['draft'] ?? null, $response['not_found'] ?? []);
            });
        } catch (LockTimeoutException) {
            Log::warning('Meal draft input waited too long for the previous one', ['user' => $userId]);
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => __('calories365-bot.draft_not_read')]);
        }
    }

    /**
     * Replace the draft message with one showing the draft; without a draft only
     * the old message goes.
     *
     * @param  array<string, mixed>|null  $draft  MealDraft::toPayload of the diary
     * @param  array<int, string>  $notFound  foods the diary found no product for
     */
    public function show($bot, $telegram, int $chatId, int $userId, ?array $draft, array $notFound = []): void
    {
        if ($draft === null) {
            $this->forget($telegram, $chatId, $userId);

            return;
        }

        $previous = Cache::get("meal_draft_message_{$userId}");

        $sent = $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $this->text($draft, $notFound),
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $this->keyboard($bot->web_app_url ?? null)]),
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
     * @param  array<string, mixed>  $draft
     * @param  array<int, string>  $notFound
     */
    private function text(array $draft, array $notFound): string
    {
        $lines = ['<b>'.e(__('calories365-bot.draft_title')).'</b>'];

        foreach ($draft['items'] as $item) {
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

        $total = $draft['total'];
        $lines[] = '';
        $lines[] = '<b>'.e(__('calories365-bot.draft_total', ['calories' => round($total['calories'])])).'</b> · '
            .e(__('calories365-bot.draft_macros', [
                'proteins' => round($total['proteins']),
                'fats' => round($total['fats']),
                'carbohydrates' => round($total['carbohydrates']),
            ]));

        if ($notFound !== []) {
            $lines[] = '';
            $lines[] = e(__('calories365-bot.draft_not_found', ['names' => implode(', ', $notFound)]));
        }

        $lines[] = '';
        $lines[] = e(__('calories365-bot.draft_hint'));

        return implode("\n", $lines);
    }

    /**
     * Breakfast, lunch and dinner, the one of the time of day first; edit, which
     * opens the draft on the "Приём пищи" page of the Mini App, and cancel.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function keyboard(?string $miniAppUrl): array
    {
        $meals = ['morning' => 'breakfast', 'dinner' => 'lunch', 'supper' => 'dinner'];
        $hour = (int) now()->format('G');
        $now = $hour < 11 ? 'morning' : ($hour < 16 ? 'dinner' : 'supper');
        $meals = [$now => $meals[$now]] + $meals;

        $row = [];
        foreach ($meals as $partOfDay => $label) {
            $row[] = ['text' => __('calories365-bot.'.$label), 'callback_data' => 'draft_save_'.$partOfDay];
        }

        $actions = [['text' => __('calories365-bot.cancel'), 'callback_data' => 'draft_cancel']];
        if ($miniAppUrl) {
            array_unshift($actions, ['text' => __('calories365-bot.draft_edit'), 'web_app' => ['url' => rtrim($miniAppUrl, '/').'/voice']]);
        }

        return [$row, $actions];
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
