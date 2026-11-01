<?php

namespace App\Services\TelegramServices\OnecHelperHandlers;

use App\Models\OnecBotMessage;
use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;

/**
 * Any message of the owner but a command: a reply to a message of the bot
 * about a question goes to that question as a follow-up, any other message to
 * the question being gathered in the chat.
 */
class CollectMessageHandler implements MessageHandlerInterface
{
    public function __construct(
        protected MessageReader $reader,
        protected QuestionCollector $collector,
        protected StartMessageHandler $startMessageHandler,
    ) {}

    public function handle($bot, $telegram, $message, $botUser)
    {
        $chatId = (int) $message->getChat()->getId();
        $text = $message['text'] ?? null;

        if (is_string($text) && str_starts_with(ltrim($text), '/')) {
            $this->startMessageHandler->handle($bot, $telegram, $message, $botUser);

            return;
        }

        $question = null;
        $replyTo = $message['reply_to_message'] ?? null;

        if (is_array($replyTo) && ($replyTo['from']['is_bot'] ?? false)) {
            $question = OnecBotMessage::questionOf($bot->id, $chatId, (int) $replyTo['message_id']);

            if ($question === null || ! $this->collector->takesReplyTo($question)) {
                $this->answer($telegram, $message, $question === null ? 'no_question' : 'no_followup');

                return;
            }
        }

        $item = $this->reader->read($bot, $telegram, $message);

        if (is_string($item)) {
            $this->answer($telegram, $message, $item);

            return;
        }

        if ($question === null) {
            $this->collector->add($bot, $telegram, $chatId, $item);
        } elseif (! $this->collector->addToQuestion($telegram, $question, $item)) {
            $this->answer($telegram, $message, 'no_followup');
        }
    }

    private function answer($telegram, $message, string $key): void
    {
        $telegram->sendMessage([
            'chat_id' => $message->getChat()->getId(),
            'text' => OnecTexts::get($key),
            'reply_parameters' => json_encode([
                'message_id' => $message->getMessageId(),
                'allow_sending_without_reply' => true,
            ]),
        ]);
    }
}
