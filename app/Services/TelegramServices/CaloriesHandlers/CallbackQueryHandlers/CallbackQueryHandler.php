<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use App\Services\TelegramServices\BaseHandlers\UpdateHandlers\UpdateHandlerInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CallbackQueryHandler implements UpdateHandlerInterface
{
    protected array $callbackQueryHandlers;

    public function __construct(array $callbackQueryHandlers)
    {
        $this->callbackQueryHandlers = $callbackQueryHandlers;
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
                    'text' => __('calories365-bot.exit_edit_mode'),
                    'show_alert' => true,
                ]);
            }
            return true;
        }

        Log::info('Unknown callback query: ' . $action);
        return true;
    }
}
