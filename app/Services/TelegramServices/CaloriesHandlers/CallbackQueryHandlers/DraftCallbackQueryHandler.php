<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use App\Services\DiaryApiService;
use App\Services\TelegramServices\CaloriesHandlers\MealDraftChat;
use App\Utilities\Utilities;

/**
 * The buttons of the meal draft message: draft_save_{morning|dinner|supper}
 * saves the meal to the diary, draft_cancel drops it.
 */
class DraftCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    public bool $blockAble = true;

    private const PARTS_OF_DAY = ['morning', 'dinner', 'supper'];

    protected DiaryApiService $diaryApiService;

    protected MealDraftChat $mealDraftChat;

    public function __construct(DiaryApiService $diaryApiService, MealDraftChat $mealDraftChat)
    {
        $this->diaryApiService = $diaryApiService;
        $this->mealDraftChat = $mealDraftChat;
    }

    public function handle($bot, $telegram, $callbackQuery, $botUser)
    {
        $parts = explode('_', (string) $callbackQuery->getData());
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $userId = $callbackQuery->getFrom()->getId();

        if (($parts[1] ?? null) === 'save' && in_array($parts[2] ?? null, self::PARTS_OF_DAY, true)) {
            $this->save($telegram, $botUser, $chatId, $userId, $parts[2]);
        } elseif (($parts[1] ?? null) === 'cancel') {
            $this->diaryApiService->draftCancel($botUser->calories_id, $botUser->locale);
            $this->mealDraftChat->forget($telegram, $chatId, $userId);
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => __('calories365-bot.draft_cancelled')]);
        }

        $telegram->answerCallbackQuery(['callback_query_id' => $callbackQuery->getId()]);
    }

    private function save($telegram, $botUser, int $chatId, int $userId, string $partOfDay): void
    {
        $response = $this->diaryApiService->draftSave($partOfDay, date('Y-m-d'), $botUser->calories_id, $botUser->locale);

        if (isset($response['error'])) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => __(($response['status'] ?? 0) === 404 ? 'calories365-bot.draft_gone' : 'calories365-bot.draft_failed'),
            ]);

            if (($response['status'] ?? 0) === 404) {
                $this->mealDraftChat->forget($telegram, $chatId, $userId);
            }

            return;
        }

        $this->mealDraftChat->forget($telegram, $chatId, $userId);

        $total = $response['total'];
        $rows = [
            [__('calories365-bot.calories'), round($total['calories'])],
            [__('calories365-bot.proteins'), round($total['proteins'])],
            [__('calories365-bot.fats'), round($total['fats'])],
            [__('calories365-bot.carbohydrates'), round($total['carbohydrates'])],
        ];

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => ($botUser->big_font ?? false)
                ? Utilities::generateTableType2ForBigFont(__('calories365-bot.data_saved_you_consumed'), $rows)
                : Utilities::generateTableType2(__('calories365-bot.data_saved_you_consumed'), $rows),
            'parse_mode' => 'Markdown',
        ]);
    }
}
