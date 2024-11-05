<?php

namespace App\Services\TelegramServices\MessageHandlers;

use App\Models\BotUser;
use App\Traits\BasicDataExtractor;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\FileUpload\InputFile;

class TextMessageHandler implements MessageHandlerInterface
{

    use BasicDataExtractor;

    protected array $textMessageHandlers;

    public function __construct($textMessageHandlers)
    {
        $this->textMessageHandlers = $textMessageHandlers;
    }

    public function handle($bot, $telegram, $message): void
    {
        $text = $message->getText();

        if (isset($this->textMessageHandlers[$text])) {
            $this->textMessageHandlers[$text]->handle($bot, $telegram, $message);
        } else {
            Log::info("Unhandled text message type: " . $text);
        }
    }
}
