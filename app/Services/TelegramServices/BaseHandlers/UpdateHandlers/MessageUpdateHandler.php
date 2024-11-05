<?php

namespace App\Services\TelegramServices\BaseHandlers\UpdateHandlers;

use Illuminate\Support\Facades\Log;

/**
 * Class MessageUpdateHandler
 *
 * This service implements UpdateHandlerInterface to handle Telegram bot messages
 * and distribute them to the appropriate handlers.
 */
class MessageUpdateHandler implements UpdateHandlerInterface
{
    protected array $messageHandlers;

    /**
     * MessageUpdateHandler constructor.
     * Initializes the message handlers.
     */
    public function __construct(array $messageHandlers)
    {
        $this->messageHandlers = $messageHandlers;
    }

    /**
     * MessageUpdateHandler handle.
     * starts the required Handler for the message.
     */
    public function handle($bot, $telegram, $update)
    {
        $message = $update->getMessage();

        foreach ($this->messageHandlers as $type => $handler) {
            if (isset($message[$type])) {
                $handler->handle($bot, $telegram, $message);
                return;
            }
        }

        Log::info("Unknown message type: " . json_encode($message));
    }
}
