<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use App\Services\TelegramServices\BaseHandlers\UpdateHandlers\UpdateHandlerInterface;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditingProcessCallbackQuery\EditingCancelCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditingProcessCallbackQuery\EditingSaveCallbackQueryHandler;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditingProcessCallbackQuery\EditingSkipCallbackQueryHandler;
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
    )
    {
        $this->callbackQueryHandlers = [
            'cancel' => $cancelCallbackQuery,
            'save' => $saveCallbackQuery,
            'edit' => $editCallbackQuery,
            'delete' => $deleteCallbackQuery,
            'editing_save' => $editingSaveCallbackQueryHandler,
            'editing_cancel' => $editingCancelCallbackQueryHandler,
            'editing_skip' => $editingSkipCallbackQueryHandler,
        ];
    }

    public function handle($bot, $telegram, $update)
    {
        $callbackQuery = $update->getCallbackQuery();

        $callbackData = $callbackQuery->getData();

        $parts = explode('_', $callbackData);

        $action = $parts[0];
        if (isset($parts[1]) && in_array($action, ['editing'])) {
            $action = $action . '_' . $parts[1];
        }

        if (isset($this->callbackQueryHandlers[$action])) {
            $handler = $this->callbackQueryHandlers[$action];
            $handler->handle($bot, $telegram, $callbackQuery);
            return true;
        }

        Log::info('Unknown callback query: ' . $action);
        return true;
    }
}
