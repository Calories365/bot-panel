<?php

namespace App\Services\TelegramServices;

use App\Interfaces\BotHandlerStrategy;
use App\Services\TelegramServices\BaseHandlers\MessageHandlers\AudioMessageHandler;
use App\Services\TelegramServices\BaseHandlers\MessageHandlers\TextMessageHandler;
use App\Services\TelegramServices\BaseHandlers\TextMessageHandlers\StartMessageHandler;
use App\Services\TelegramServices\BaseHandlers\UpdateHandlers\CallbackQueryHandler;
use App\Services\TelegramServices\BaseHandlers\UpdateHandlers\MessageUpdateHandler;
use App\Services\TelegramServices\BaseHandlers\UpdateHandlers\MyChatMemberUpdateHandler;
use Illuminate\Support\Facades\Log;

/**
 * Class BaseService
 *
 * This service implements BotHandlerStrategy to handle Telegram bot updates
 * and distribute them to the appropriate handlers.
 */
class BaseService implements BotHandlerStrategy
{
    protected array $updateHandlers;

    /**
     * BaseService constructor.
     * Initializes the update handlers by calling the getUpdateHandlers method.
     */
    public function __construct()
    {
        $this->updateHandlers = $this->getUpdateHandlers();
    }

    /**
     * BaseService getUpdateHandlers.
     * Collects and returns base UpdateHandlers and for each UpdateHandler passes base subhandlers
     */
    protected function getUpdateHandlers(): array
    {
        $messageUpdateHandler = new ($this->getMessageHandlers());
        $myChatMemberUpdateHandler = new MyChatMemberUpdateHandler();
        $callbackQueryHandler = new CallbackQueryHandler($this->getCallbackQueryHandlers());

        return [
            'message' => $messageUpdateHandler,
            'my_chat_member' => $myChatMemberUpdateHandler,
            'callback_query' => $callbackQueryHandler
        ];
    }

    /**
     * BaseService getMessageHandlers.
     * collects and returns all basic MessageHandlers
     */
    protected function getMessageHandlers(): array
    {
        $textMessageHandler = new TextMessageHandler(
            $this->getTextMessageHandlers()
        );
        $audioMessageHandler = new AudioMessageHandler();

        return [
            'text' => $textMessageHandler,
            'voice' => $audioMessageHandler,
        ];
    }

    /**
     * BaseService getTextMessageHandlers.
     * collects and returns all basic TextMessageHandlers
     */
    protected function getTextMessageHandlers(): array
    {
        $startTextMessageHandler = new StartMessageHandler();

        return [
            '/start' => $startTextMessageHandler,
            '/default' => $startTextMessageHandler
        ];
    }

    /**
     * BaseService getCallbackQueryHandlers.
     * collects and returns all basic CallbackQueryHandlers
     */
    protected function getCallbackQueryHandlers(): array{

        $callbackQueryHandler = new CallbackQueryHandler();

        return [

        ];
    }

    /**
     * BaseService handle.
     * starts the required Handler for the event
     */
    public function handle($bot, $telegram, $update, $botUser): void
    {
        $updateType = $update->detectType();
        if (isset($this->updateHandlers[$updateType])) {
            $this->updateHandlers[$updateType]->handle($bot, $telegram, $update, $botUser);
        } else {
            Log::info("Unhandled update type: " . $updateType);
        }
    }
}
