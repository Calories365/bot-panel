<?php

namespace App\Services\TelegramServices\BaseHandlers\MessageHandlers;

use App\Traits\BasicDataExtractor;
use Illuminate\Support\Facades\Cache;

class TextMessageHandler
{
    use BasicDataExtractor;

    protected array $textMessageHandlers;

    public function __construct($textMessageHandlers)
    {
        $this->textMessageHandlers = $textMessageHandlers;
    }

    public function handle($bot, $telegram, $message, $botUser): void
    {
        $text = $message->getText();
        $userId = $message->getFrom()->getId();
        $chatId = $message->getChat()->getId();

        $commandParts = explode(' ', $text);

        if ($commandParts[0] == '/start') {
            $text = $commandParts[0];
        }

        if (isset($this->textMessageHandlers[$text])) {
            $isBlocked = Cache::get("command_block{$userId}", 0);
            if (! $isBlocked) {
                $this->textMessageHandlers[$text]->handle($bot, $telegram, $message, $botUser);
            } else {
                $sentMessage = $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'действие невозможно',
                    'parse_mode' => 'Markdown',
                ]);

                return;
            }
        } else {
            $this->textMessageHandlers['default']->handle($bot, $telegram, $message, $botUser);
        }
    }
}
