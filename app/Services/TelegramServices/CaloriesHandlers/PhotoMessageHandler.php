<?php

namespace App\Services\TelegramServices\CaloriesHandlers;

use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;
use App\Traits\BasicDataExtractor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * A photo of a dish or of a label, with its caption, sent as a photo or as a file:
 * it waits in the diary for the calculation of the meal draft (MealDraftChat). The
 * file goes to the diary itself, never its Telegram link, which holds the bot
 * token, and made small enough for the diary's nginx, which takes up to 1 MB.
 * Keeping it calls no model, so it does not count for the spam guard; the
 * calculation does.
 */
class PhotoMessageHandler implements MessageHandlerInterface
{
    use BasicDataExtractor;

    /**
     * A file above this size is made smaller: the body of the request holds more
     * than the file, and the limit of the diary's nginx is 1 MB.
     */
    private const MAX_BYTES = 900 * 1024;

    /**
     * The longest side of a photo made smaller: labels stay legible.
     */
    private const MAX_SIDE = 1600;

    /**
     * JPEG qualities of ffmpeg to try, from the best one.
     */
    private const JPEG_QUALITIES = [4, 8, 14];

    /**
     * What the diary takes as it is.
     */
    private const DIARY_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    protected MealDraftChat $mealDraftChat;

    public function __construct(MealDraftChat $mealDraftChat)
    {
        $this->mealDraftChat = $mealDraftChat;
    }

    public function handle($bot, $telegram, $message, $botUser)
    {
        $commonData = self::extractCommonData($message);
        $chatId = $commonData['chatId'];

        // Telegram sends a photo in several sizes, the largest last.
        $size = collect($message['photo'] ?? [])->last() ?? $message['document'] ?? null;

        if ($size === null) {
            return;
        }

        $photo = $this->download($telegram, $bot, (string) $size['file_id']);
        $photo = $photo === null ? null : $this->fitForDiary($photo);

        if ($photo === null) {
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => __('calories365-bot.photo_not_downloaded')]);

            return;
        }

        $this->mealDraftChat->collect($bot, $telegram, $botUser, $chatId, $commonData['userId'], 'photo', $message['caption'] ?? null, $photo);
    }

    /**
     * @return array{contents: string, name: string}|null
     */
    private function download($telegram, $bot, string $fileId): ?array
    {
        try {
            $path = $telegram->getFile(['file_id' => $fileId])->file_path;
            $response = Http::timeout(45)->get('https://api.telegram.org/file/bot'.$bot->token.'/'.$path);
        } catch (\Throwable $e) {
            Log::error('Cannot download the photo', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->ok()) {
            Log::error('Cannot download the photo', ['status' => $response->status()]);

            return null;
        }

        return ['contents' => $response->body(), 'name' => basename((string) $path)];
    }

    /**
     * The photo as a JPEG the diary takes: a big photo or a file in another format
     * (an iPhone sends HEIC as a file) is converted; null when it cannot be read.
     *
     * @param  array{contents: string, name: string}  $photo
     * @return array{contents: string, name: string}|null
     */
    private function fitForDiary(array $photo): ?array
    {
        $extension = strtolower(pathinfo($photo['name'], PATHINFO_EXTENSION));

        if (strlen($photo['contents']) <= self::MAX_BYTES && in_array($extension, self::DIARY_EXTENSIONS, true)) {
            return $photo;
        }

        $source = tempnam(sys_get_temp_dir(), 'meal-photo-');
        $target = $source.'.jpg';

        try {
            file_put_contents($source, $photo['contents']);

            $side = self::MAX_SIDE;

            // A photo full of fine detail needs a stronger compression to fit.
            foreach (self::JPEG_QUALITIES as $quality) {
                $result = Process::timeout(30)->run([
                    'ffmpeg', '-y', '-loglevel', 'error', '-i', $source,
                    '-vf', "scale='min({$side},iw)':'min({$side},ih)':force_original_aspect_ratio=decrease",
                    '-frames:v', '1', '-q:v', (string) $quality, $target,
                ]);

                if (! $result->successful() || ! is_file($target)) {
                    Log::error('Cannot convert the photo', ['error' => $result->errorOutput()]);

                    return null;
                }

                clearstatcache(true, $target);
                if (filesize($target) <= self::MAX_BYTES) {
                    return ['contents' => (string) file_get_contents($target), 'name' => pathinfo($photo['name'], PATHINFO_FILENAME).'.jpg'];
                }
            }

            Log::error('Cannot make the photo small enough for the diary');

            return null;
        } finally {
            @unlink($source);
            @unlink($target);
        }
    }
}
