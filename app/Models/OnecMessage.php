<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One message of a question: a text, a photo or a file with its caption, or the
 * words of a voice message. Its file, when it has one, is on the panel's disk.
 *
 * @property int $id
 * @property int $batch_id
 * @property int $telegram_message_id
 * @property string $type text|photo|document|voice
 * @property string|null $text
 * @property string|null $author
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property bool $forwarded
 * @property string|null $media_group_id
 * @property bool $is_screen a photo or a picture sent as a file
 * @property string|null $file_path
 * @property string|null $file_name
 * @property string|null $file_mime
 * @property int|null $file_size
 * @property \Illuminate\Support\Carbon|null $file_deleted_at
 * @property-read OnecBatch $batch
 */
class OnecMessage extends Model
{
    public const TYPE_TEXT = 'text';

    public const TYPE_PHOTO = 'photo';

    public const TYPE_DOCUMENT = 'document';

    public const TYPE_VOICE = 'voice';

    protected $fillable = [
        'batch_id',
        'telegram_message_id',
        'type',
        'text',
        'author',
        'sent_at',
        'forwarded',
        'media_group_id',
        'is_screen',
        'file_path',
        'file_name',
        'file_mime',
        'file_size',
        'file_deleted_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'forwarded' => 'boolean',
        'is_screen' => 'boolean',
        'file_deleted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<OnecBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(OnecBatch::class, 'batch_id');
    }

    public function hasFile(): bool
    {
        return in_array($this->type, [self::TYPE_PHOTO, self::TYPE_DOCUMENT], true);
    }

    /**
     * Delete the file from the disk; the record of the message stays.
     */
    public function deleteFile(): void
    {
        if ($this->file_path === null || $this->file_deleted_at !== null) {
            return;
        }

        Storage::disk(config('onec_helper.disk'))->delete($this->file_path);

        $this->update(['file_deleted_at' => now()]);
    }
}
