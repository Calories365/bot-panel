<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use App\Services\TelegramServices\BaseHandlers\UpdateHandlers\UpdateHandlerInterface;
use Illuminate\Support\Facades\Log;

class CallbackQueryHandler implements UpdateHandlerInterface
{
    protected array $callbackQueryHandlers;

    public function __construct(
        CancelCallbackQueryHandler $cancelCallbackQuery,
        SaveCallbackQueryHandler   $saveCallbackQuery,
        EditCallbackQueryHandler   $editCallbackQuery,
        DeleteCallbackQueryHandler $deleteCallbackQuery,
        EditActionCallbackQueryHandler $editActionCallbackQueryHandler
    )
    {
        $this->callbackQueryHandlers = [
            'cancel' => $cancelCallbackQuery,
            'save' => $saveCallbackQuery,
            'edit' => $editCallbackQuery,
            'delete' => $deleteCallbackQuery,
        ];
    }

    public function handle($bot, $telegram, $update)
    {
        $callbackQuery = $update->getCallbackQuery();

        $callbackData = $callbackQuery->getData();

        $parts = explode('_', $callbackData);

        $action = $parts[0];

        if (isset($this->callbackQueryHandlers[$action])) {
            $handler = $this->callbackQueryHandlers[$action];
            $handler->handle($bot, $telegram, $callbackQuery);
            return true;
        }

        Log::info('Unknown callback query: ' . $action);
        return true;
    }
}
