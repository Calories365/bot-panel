<?php

namespace App\Services\TelegramServices\CaloriesHandlers;

use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;

/**
 * A file: a photo sent "as a file" goes the way of a photo, an audio file the way
 * of a voice message; the bot says what it understands about the rest.
 */
class DocumentMessageHandler implements MessageHandlerInterface
{
    protected PhotoMessageHandler $photoMessageHandler;

    protected AudioMessageHandler $audioMessageHandler;

    protected UnsupportedMessageHandler $unsupportedMessageHandler;

    public function __construct(
        PhotoMessageHandler $photoMessageHandler,
        AudioMessageHandler $audioMessageHandler,
        UnsupportedMessageHandler $unsupportedMessageHandler
    ) {
        $this->photoMessageHandler = $photoMessageHandler;
        $this->audioMessageHandler = $audioMessageHandler;
        $this->unsupportedMessageHandler = $unsupportedMessageHandler;
    }

    public function handle($bot, $telegram, $message, $botUser)
    {
        $mime = (string) ($message['document']['mime_type'] ?? '');

        $handler = match (true) {
            str_starts_with($mime, 'image/') => $this->photoMessageHandler,
            str_starts_with($mime, 'audio/') => $this->audioMessageHandler,
            default => $this->unsupportedMessageHandler,
        };

        $handler->handle($bot, $telegram, $message, $botUser);
    }
}
