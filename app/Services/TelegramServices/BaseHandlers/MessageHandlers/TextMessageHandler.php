<?php

namespace App\Services\TelegramServices\BaseHandlers\MessageHandlers;

use App\Traits\BasicDataExtractor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        $userId = $message->getFrom()->getId();
        $chatId = $message->getChat()->getId();

         $commandParts = explode('_', $text);
//         if (isset($commandParts[1])) {
//             $text = $commandParts[0];
//         }

        $parts = explode(' ', $commandParts[0]);
        $text = $parts[0];


        if (isset($this->textMessageHandlers[$text])) {
            $isBlocked = Cache::get("command_block{$userId}", 0);
            if (!$isBlocked){
                $this->textMessageHandlers[$text]->handle($bot, $telegram, $message);
            } else {
                $sentMessage = $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'действие невозможно',
                    'parse_mode' => 'Markdown',
                ]);
                return;
            }
        } else {
            $this->textMessageHandlers['default']->handle($bot, $telegram, $message);
        }
    }
}
