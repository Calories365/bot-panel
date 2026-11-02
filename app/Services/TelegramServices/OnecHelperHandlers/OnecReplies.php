<?php

namespace App\Services\TelegramServices\OnecHelperHandlers;

use App\Models\OnecBatch;
use App\Models\OnecBotMessage;
use App\Models\OnecJob;
use App\Models\OnecQuestion;
use Telegram\Bot\Api;

/**
 * What the 1C helper bot sends about a question after it is gathered: the
 * answer, the text for the boss, a failure, where the session opened on the
 * server is. Each goes as a reply to the service message of its question or
 * follow-up, or, for a job without a batch, to the message its button was
 * under. Without parse_mode unless asked: the texts of the worker hold any
 * characters. The bot remembers every message it sends, so that a reply to
 * it finds the question.
 */
class OnecReplies
{
    /**
     * Telegram refuses a message longer than 4096 characters.
     */
    public const MESSAGE_LIMIT = 4096;

    /**
     * @param  list<string|null>  $texts  an empty one is not sent
     * @param  list<list<array<string, string>>>  $keyboard  inline buttons under the first message sent
     */
    public function about(Api $telegram, OnecJob $job, array $texts, array $keyboard = [], ?string $parseMode = null): void
    {
        $replyTo = $job->batch->service_message_id ?? $job->reply_to_message_id;

        $this->sendTo($telegram, $job->question, $job->batch_id, $replyTo, $texts, $keyboard, $parseMode);
    }

    /**
     * For the replies queued before they were sent about a job.
     *
     * @param  list<string|null>  $texts
     * @param  list<list<array<string, string>>>  $keyboard
     */
    public function send(Api $telegram, OnecBatch $batch, array $texts, array $keyboard = []): void
    {
        $this->sendTo($telegram, $batch->question, $batch->id, $batch->service_message_id, $texts, $keyboard);
    }

    public function remember(OnecBatch $batch, int $messageId): void
    {
        $this->rememberFor($batch->question, $batch->id, $messageId);
    }

    /**
     * @param  list<string|null>  $texts
     * @param  list<list<array<string, string>>>  $keyboard
     */
    private function sendTo(Api $telegram, OnecQuestion $question, ?int $batchId, ?int $replyTo, array $texts, array $keyboard = [], ?string $parseMode = null): void
    {
        $parts = [];
        foreach ($texts as $text) {
            array_push($parts, ...self::split((string) $text));
        }

        foreach ($parts as $index => $part) {
            $withKeyboard = $keyboard !== [] && $index === 0;

            $sent = $telegram->sendMessage(['chat_id' => $question->chat_id, 'text' => $part]
                + ($parseMode === null ? [] : ['parse_mode' => $parseMode])
                + ($replyTo === null ? [] : ['reply_parameters' => json_encode([
                    'message_id' => $replyTo,
                    'allow_sending_without_reply' => true,
                ])])
                + ($withKeyboard ? ['reply_markup' => json_encode(['inline_keyboard' => $keyboard])] : []));

            $this->rememberFor($question, $batchId, (int) $sent->getMessageId());
        }
    }

    private function rememberFor(OnecQuestion $question, ?int $batchId, int $messageId): void
    {
        OnecBotMessage::create([
            'question_id' => $question->id,
            'batch_id' => $batchId,
            'chat_id' => $question->chat_id,
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
