<?php

namespace App\Services\TelegramServices\OnecHelperHandlers;

use App\Models\OnecBatch;
use App\Models\OnecBotMessage;
use Telegram\Bot\Api;

/**
 * What the 1C helper bot sends about a question after it is gathered: the
 * answer, the text for the boss, a failure. Each goes as a reply to the
 * service message of its question or follow-up, without parse_mode: the
 * texts hold any characters. The bot remembers every message it sends, so
 * that a reply to it finds the question.
 */
class OnecReplies
{
    /**
     * Telegram refuses a message longer than 4096 characters.
     */
    public const MESSAGE_LIMIT = 4096;

    /**
     * @param  list<string|null>  $texts  an empty one is not sent
     */
    public function send(Api $telegram, OnecBatch $batch, array $texts): void
    {
        foreach ($texts as $text) {
            foreach (self::split((string) $text) as $part) {
                $sent = $telegram->sendMessage([
                    'chat_id' => $batch->question->chat_id,
                    'text' => $part,
                    'reply_parameters' => json_encode([
                        'message_id' => $batch->service_message_id,
                        'allow_sending_without_reply' => true,
                    ]),
                ]);

                $this->remember($batch, (int) $sent->getMessageId());
            }
        }
    }

    public function remember(OnecBatch $batch, int $messageId): void
    {
        OnecBotMessage::create([
            'question_id' => $batch->question_id,
            'batch_id' => $batch->id,
            'chat_id' => $batch->question->chat_id,
            'message_id' => $messageId,
        ]);
    }

    /**
     * The text in parts Telegram takes, split between paragraphs; a paragraph
     * longer than a message between lines, then between words, and a word
     * longer than a message anywhere.
     *
     * @return list<string>
     */
    public static function split(string $text, int $limit = self::MESSAGE_LIMIT): array
    {
        $parts = self::splitBy($text, $limit, ["\n\n", "\n", ' ']);

        return array_values(array_filter($parts, fn (string $part) => trim($part) !== ''));
    }

    /**
     * @param  list<string>  $separators
     * @return list<string>
     */
    private static function splitBy(string $text, int $limit, array $separators): array
    {
        if (mb_strlen($text) <= $limit) {
            return [$text];
        }

        if ($separators === []) {
            return mb_str_split($text, $limit);
        }

        $separator = array_shift($separators);
        $parts = [];
        $current = null;

        foreach (explode($separator, $text) as $piece) {
            $joined = $current === null ? $piece : $current.$separator.$piece;

            if (mb_strlen($joined) <= $limit) {
                $current = $joined;

                continue;
            }

            if ($current !== null) {
                $parts[] = $current;
            }

            $pieces = self::splitBy($piece, $limit, $separators);
            $current = array_pop($pieces);
            array_push($parts, ...$pieces);
        }

        if ($current !== null) {
            $parts[] = $current;
        }

        return $parts;
    }
}
