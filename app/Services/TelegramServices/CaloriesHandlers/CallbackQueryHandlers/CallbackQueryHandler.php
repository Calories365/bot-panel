<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use App\Services\TelegramServices\BaseHandlers\UpdateHandlers\UpdateHandlerInterface;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditingProcessCallbackQuery\EditingCancelCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditingProcessCallbackQuery\EditingSaveCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditingProcessCallbackQuery\EditingSkipCallbackQueryHandler;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CallbackQueryHandler implements UpdateHandlerInterface
{
    protected array $callbackQueryHandlers;

    public function __construct(
        CancelCallbackQueryHandler        $cancelCallbackQuery,
        SaveCallbackQueryHandler          $saveCallbackQuery,
        EditCallbackQueryHandler          $editCallbackQuery,
        DeleteCallbackQueryHandler        $deleteCallbackQuery,
        EditingSaveCallbackQueryHandler   $editingSaveCallbackQueryHandler,
        EditingCancelCallbackQueryHandler $editingCancelCallbackQueryHandler,
        EditingSkipCallbackQueryHandler   $editingSkipCallbackQueryHandler,
        SearchCallbackQueryHandler      $searchCallbackQueryHandler,
        DeleteMealCallbackQueryHandler $deleteMealCallbackQueryHandler,
    )
    {
        $this->callbackQueryHandlers = [
            'cancel' => $cancelCallbackQuery,
            'save' => $saveCallbackQuery,
            'edit' => $editCallbackQuery,
            'destroy' => $deleteCallbackQuery,
            'editing_save' => $editingSaveCallbackQueryHandler,
            'editing_cancel' => $editingCancelCallbackQueryHandler,
            'editing_skip' => $editingSkipCallbackQueryHandler,
            'search' => $searchCallbackQueryHandler,
            'delete_meal' => $deleteMealCallbackQueryHandler,
        ];
    }

    public function handle($bot, $telegram, $update, $botUser)
    {
        $callbackQuery = $update->getCallbackQuery();

        $callbackData = $callbackQuery->getData();

        $userId = $callbackQuery->getFrom()->getId();

        $parts = explode('_', $callbackData);

        $action = $parts[0];
        if (isset($parts[1]) && in_array($action, ['editing', 'delete'])) {
            $action = $action . '_' . $parts[1];
        }

        if (isset($this->callbackQueryHandlers[$action])) {

            $handler = $this->callbackQueryHandlers[$action];
            $isBlocked = Cache::get("command_block{$userId}", 0);

            if (!$isBlocked || !$handler->blockAble) {

                $handler->handle($bot, $telegram, $callbackQuery, $botUser);
            } else {

                $telegram->answerCallbackQuery([
                    'callback_query_id' => $callbackQuery->getId(),
                    'text' => 'Выйдите с режима редактирование(нажмите сохранить или отменить).',
                    'show_alert' => true,
                ]);
            }
            return true;
        }

        Log::info('Unknown callback query: ' . $action);
        return true;
    }
}
