<?php

namespace App\Services\TelegramServices\TranscriptionHandlers;

use App\Services\AudioConversionService;
use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;

/**
 * Answers a voice message with its text, as a reply to it: in a chat of many
 * voice messages it shows which text belongs to which.
 */
class VoiceMessageHandler implements MessageHandlerInterface
{
    /**
     * Telegram refuses a message longer than 4096 characters.
     */
    private const MESSAGE_LIMIT = 4096;

    public function __construct(protected AudioConversionService $audioConversionService) {}

    public function handle($bot, $telegram, $message, $botUser)
    {
        $chatId = $message->getChat()->getId();
        $replyParameters = json_encode([
            'message_id' => $message->getMessageId(),
            'allow_sending_without_reply' => true,
        ]);

        $telegram->sendChatAction(['chat_id' => $chatId, 'action' => 'typing']);

        $text = $this->audioConversionService->processAudioMessage($telegram, $bot, $message, false);

        if ($text === null) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => __('transcription-bot.failed'),
                'reply_parameters' => $replyParameters,
            ]);

            return;
        }

        foreach (mb_str_split($text, self::MESSAGE_LIMIT) as $part) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $part,
                'reply_parameters' => $replyParameters,
            ]);
        }
    }
}
