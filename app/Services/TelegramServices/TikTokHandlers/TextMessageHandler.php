<?php

namespace App\Services\TelegramServices\TikTokHandlers;

use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;
use App\Traits\BasicDataExtractor;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\FileUpload\InputFile;

class TextMessageHandler implements MessageHandlerInterface
{
    use BasicDataExtractor;

    public function handle($bot, $telegram, $message, $botUser)
    {
        $text = trim($message->getText());

        $host = parse_url($text, PHP_URL_HOST);
        if (! $host || mb_stripos($host, 'tiktok.com') === false) {

            $telegram->sendMessage([
                'chat_id' => $message->getChat()->getId(),
                'text' => 'Пожалуйста, пришлите ссылку на видео TikTok.',
            ]);

            return true;
        }

        $client = new Client(['timeout' => 10]);

        try {

            $response = $client->get('https://tiktok-video-no-watermark2.p.rapidapi.com/', [

                'query' => ['url' => $text],
                'headers' => [
                    'x-rapidapi-host' => 'tiktok-video-no-watermark2.p.rapidapi.com',
                    'x-rapidapi-key' => 'b59d4eed6bmsh4df1272cf3c7b4bp14fb27jsna72fdabadf65',
                ],
            ]);
        } catch (GuzzleException $e) {
            Log::error("TikTok API request failed: {$e->getMessage()}");
            $telegram->sendMessage([
                'chat_id' => $message->getChat()->getId(),
                'text' => 'Не удалось связаться с API. Попробуйте повторить через минуту.',
            ]);

            return true;
        }

        $result = json_decode($response->getBody()->getContents(), true);

        $payload = $result['data'] ?? null;
        if (! is_array($payload)) {
            $telegram->sendMessage([
                'chat_id' => $message->getChat()->getId(),
                'text' => 'Неверный формат ответа от API.',
            ]);

            return true;
        }

        $images = $this->extractImages($payload);
        if ($images) {
            $this->sendSlideshow($telegram, $message->getChat()->getId(), $images, $payload);

            return true;
        }

        $videoUrl = $payload['play'] ?? $payload['wmplay'] ?? null;
        if (! $videoUrl) {
            $telegram->sendMessage([
                'chat_id' => $message->getChat()->getId(),
                'text' => 'В ответе API не нашлось ссылки на видео.',
            ]);

            return true;
        }

        $telegram->sendVideo([
            'chat_id' => $message->getChat()->getId(),
            'video' => InputFile::create($videoUrl),
        ]);

        return true;
    }

    /**
     * Photo posts (a slideshow of images with music) have no video: the API
     * returns only a blank clip with the sound for them.
     */
    private function extractImages(array $payload): array
    {
        $images = $payload['images'] ?? $payload['image_post_info']['images'] ?? [];

        if (! is_array($images)) {
            return [];
        }

        $urls = [];

        foreach ($images as $image) {
            $url = is_array($image)
                ? ($image['url'] ?? $image['url_list'][0] ?? $image['display_image']['url_list'][0] ?? null)
                : $image;

            if (is_string($url) && $url !== '') {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * Sends the pictures as albums and the soundtrack as an audio file.
     */
    private function sendSlideshow($telegram, $chatId, array $images, array $payload): void
    {
        foreach (array_chunk($images, 10) as $chunk) {
            $this->sendImages($telegram, $chatId, $chunk);
        }

        $musicUrl = $payload['music_info']['play'] ?? $payload['music'] ?? null;
        if (! $musicUrl) {
            return;
        }

        try {
            $telegram->sendAudio(array_filter([
                'chat_id' => $chatId,
                'audio' => InputFile::create($musicUrl, 'tiktok-audio.mp3'),
                'title' => $payload['music_info']['title'] ?? null,
                'performer' => $payload['music_info']['author'] ?? null,
            ]));
        } catch (\Throwable $e) {
            Log::error("TikTok music sending failed: {$e->getMessage()}");
        }
    }

    /**
     * Telegram accepts from 2 to 10 pictures in one album.
     */
    private function sendImages($telegram, $chatId, array $urls): void
    {
        try {
            if (count($urls) === 1) {
                $telegram->sendPhoto([
                    'chat_id' => $chatId,
                    'photo' => InputFile::create($urls[0], 'photo.jpg'),
                ]);

                return;
            }

            $telegram->sendMediaGroup([
                'chat_id' => $chatId,
                'media' => json_encode(array_map(
                    static fn ($url) => ['type' => 'photo', 'media' => $url],
                    $urls
                )),
            ]);
        } catch (\Throwable $e) {
            Log::error("TikTok album sending failed: {$e->getMessage()}");
        }
    }
}
