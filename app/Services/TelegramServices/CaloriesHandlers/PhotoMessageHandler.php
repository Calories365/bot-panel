<?php

namespace App\Services\TelegramServices\CaloriesHandlers;

use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;
use App\Traits\BasicDataExtractor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A photo of a dish or of a label, with its caption: it goes to the meal draft
 * (MealDraftChat). The file goes to the diary itself, never its Telegram link,
 * which holds the bot token.
 */
class PhotoMessageHandler implements MessageHandlerInterface
{
    use BasicDataExtractor;

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
        $size = collect($message['photo'] ?? [])->last();

        if ($size === null || ! $this->mealDraftChat->allowed($telegram, $botUser, $chatId)) {
            return;
        }

        $photo = $this->download($telegram, $bot, (string) $size['file_id']);

        if ($photo === null) {
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => __('calories365-bot.photo_not_downloaded')]);

            return;
        }

        $this->mealDraftChat->input($bot, $telegram, $botUser, $chatId, $commonData['userId'], $message['caption'] ?? null, $photo);
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
}
