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
}
