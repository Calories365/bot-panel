<?php

namespace App\Services\TelegramServices\OnecHelperHandlers;

use App\Models\Bot;
use App\Models\OnecMessage;
use App\Services\AudioConversionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Message;

/**
 * A message to the 1C helper bot as the attributes of an OnecMessage: a text,
 * a photo in its largest size or a file downloaded to the panel's disk with its
 * caption, a voice message as its words. A forwarded message keeps its author
 * and the time it was written.
 */
class MessageReader
{
    public function __construct(protected AudioConversionService $audioConversionService) {}

    /**
     * The attributes, or the key of the text telling why the message could
     * not be read.
     *
     * @return array<string, mixed>|string
     */
    public function read(Bot $bot, Api $telegram, Message $message): array|string
    {
        $raw = $message->toArray();

        $item = [
            'telegram_message_id' => (int) $raw['message_id'],
            'media_group_id' => isset($raw['media_group_id']) ? (string) $raw['media_group_id'] : null,
            ...$this->origin($raw),
        ];

        if (isset($raw['voice']) || isset($raw['audio'])) {
            // Without the language hint: the voice messages are Ukrainian,
            // and the model detects the language itself.
            $text = $this->audioConversionService->processAudioMessage($telegram, $bot, $message, false);

            return $text === null ? 'voice_failed' : $item + ['type' => OnecMessage::TYPE_VOICE, 'text' => $text];
        }

        if (! empty($raw['photo'])) {
            $largest = collect($raw['photo'])->sortBy(fn (array $size) => ($size['width'] ?? 0) * ($size['height'] ?? 0))->last();
            $file = $this->download($bot, $telegram, (string) $largest['file_id']);

            return $file === null ? 'file_failed' : $item + [
                'type' => OnecMessage::TYPE_PHOTO,
                'text' => $raw['caption'] ?? null,
                'is_screen' => true,
                'file_path' => $file['path'],
                'file_name' => $file['name'],
                'file_mime' => 'image/jpeg',
                'file_size' => $file['size'],
            ];
        }

        $document = $raw['document'] ?? $raw['video'] ?? $raw['animation'] ?? null;

        if (is_array($document)) {
            $file = $this->download($bot, $telegram, (string) $document['file_id']);
            $mime = (string) ($document['mime_type'] ?? 'application/octet-stream');

            return $file === null ? 'file_failed' : $item + [
                'type' => OnecMessage::TYPE_DOCUMENT,
                'text' => $raw['caption'] ?? null,
                'is_screen' => str_starts_with($mime, 'image/'),
                'file_path' => $file['path'],
                'file_name' => (string) ($document['file_name'] ?? $file['name']),
                'file_mime' => $mime,
                'file_size' => $file['size'],
            ];
        }

        if (isset($raw['text'])) {
            return $item + ['type' => OnecMessage::TYPE_TEXT, 'text' => (string) $raw['text']];
        }

        return 'unsupported';
    }

    /**
     * Who wrote the message and when: for a forwarded one, its author in
     * forward_origin (or the fields before it) and its own time.
     *
     * @param  array<string, mixed>  $raw
     * @return array{author: string|null, sent_at: Carbon, forwarded: bool}
     */
    private function origin(array $raw): array
    {
        $origin = $raw['forward_origin'] ?? null;

        if (is_array($origin)) {
            $author = match ($origin['type'] ?? null) {
                'user' => $this->name($origin['sender_user'] ?? []),
                'hidden_user' => $origin['sender_user_name'] ?? null,
                'chat' => $this->signed($origin['sender_chat']['title'] ?? null, $origin['author_signature'] ?? null),
                'channel' => $this->signed($origin['chat']['title'] ?? null, $origin['author_signature'] ?? null),
                default => null,
            };

            return ['author' => $author, 'sent_at' => $this->time($origin['date'] ?? $raw['date']), 'forwarded' => true];
        }

        if (isset($raw['forward_date'])) {
            $author = isset($raw['forward_from'])
                ? $this->name($raw['forward_from'])
                : ($raw['forward_sender_name'] ?? $this->signed($raw['forward_from_chat']['title'] ?? null, $raw['forward_signature'] ?? null));

            return ['author' => $author, 'sent_at' => $this->time($raw['forward_date']), 'forwarded' => true];
        }

        return ['author' => $this->name($raw['from'] ?? []), 'sent_at' => $this->time($raw['date']), 'forwarded' => false];
    }

    /**
     * @param  array<string, mixed>  $user
     */
    private function name(array $user): ?string
    {
        $name = trim(($user['first_name'] ?? '').' '.($user['last_name'] ?? ''));

        return $name !== '' ? $name : ($user['username'] ?? null);
    }

    private function signed(?string $title, ?string $signature): ?string
    {
        return $signature ? trim($title.' ('.$signature.')') : $title;
    }

    private function time(int|string $timestamp): Carbon
    {
        return Carbon::createFromTimestamp((int) $timestamp, config('app.timezone'));
    }

    /**
     * Download the file from Telegram to the panel's disk. Its Telegram link
     * holds the bot token, so it is never logged.
     *
     * @return array{path: string, name: string, size: int}|null
     */
    private function download(Bot $bot, Api $telegram, string $fileId): ?array
    {
        try {
            $telegramPath = (string) $telegram->getFile(['file_id' => $fileId])->file_path;
            $response = Http::timeout(60)->get('https://api.telegram.org/file/bot'.$bot->token.'/'.$telegramPath);
        } catch (\Throwable $e) {
            Log::error('The 1C helper bot could not download a file', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->ok()) {
            Log::error('The 1C helper bot could not download a file', ['status' => $response->status()]);

            return null;
        }

        $extension = strtolower(pathinfo($telegramPath, PATHINFO_EXTENSION));
        $path = config('onec_helper.directory').'/'.Str::uuid().(preg_match('/^[a-z0-9]{1,10}$/', $extension) ? '.'.$extension : '');
        $contents = $response->body();

        // The queue worker writes as root and php-fpm reads as www-data, and the disk
        // makes a new directory 0700 unless told otherwise. "public" is the mode of
        // the file here, not a way to it from the web: the disk is outside public/.
        $stored = Storage::disk(config('onec_helper.disk'))->put($path, $contents, [
            'visibility' => 'public',
            'directory_visibility' => 'public',
        ]);

        if (! $stored) {
            Log::error('The 1C helper bot could not store a file', ['path' => $path]);

            return null;
        }

        return ['path' => $path, 'name' => basename($telegramPath), 'size' => strlen($contents)];
    }
}
