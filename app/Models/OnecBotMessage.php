<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message the 1C helper bot sent about a question: its service message, the
 * answer, the text for the boss. A reply to it opens a follow-up.
 *
 * @property int $id
 * @property int $question_id
 * @property int|null $batch_id
 * @property int $chat_id
 * @property int $message_id
 * @property-read OnecQuestion $question
 */
class OnecBotMessage extends Model
{
    protected $fillable = [
        'question_id',
        'batch_id',
        'chat_id',
        'message_id',
    ];

    /**
     * @return BelongsTo<OnecQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(OnecQuestion::class, 'question_id');
    }

    /**
     * The question of a message the bot sent in the chat, if it is one.
     */
    public static function questionOf(int $botId, int $chatId, int $messageId): ?OnecQuestion
    {
        return static::where('chat_id', $chatId)
            ->where('message_id', $messageId)
            ->whereHas('question', fn ($q) => $q->where('bot_id', $botId))
            ->latest('id')
            ->first()
            ?->question;
    }
}
