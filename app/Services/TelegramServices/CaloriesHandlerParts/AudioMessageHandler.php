<?php

namespace App\Services\TelegramServices\CaloriesHandlerParts;

use App\Services\APIService\DiaryApiService;
use App\Services\ChatGPTServices\SpeechToTextService;
use App\Traits\BasicDataExtractor;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AudioMessageHandler
{
    use BasicDataExtractor;

    public static function handle($bot, $telegram, $update)
    {
        $message = $update->getMessage();
        $commonData = self::extractCommonData($message);

        if (isset($message['voice'])) {
            //обработка аудио сообщения
            $audio = $message['voice'];
            $fileId = $audio['file_id'];
            $file = $telegram->getFile(['file_id' => $fileId]);
            $filePath = $file->getFilePath();
            $token = $bot->token;
            $downloadLink = "https://api.telegram.org/file/bot" . $token . "/" . $filePath;

            //скачивание аудио сообщения
            $contents = file_get_contents($downloadLink);
            $localPath = 'audios/' . basename($filePath);
            $fullLocalPath = Storage::disk('public')->path($localPath);
            Storage::disk('public')->put($localPath, $contents);

            try {
                //конвертация аудио сообщения в mp3
                $ffmpeg = FFMpeg::create([
                    'ffmpeg.binaries' => '/opt/homebrew/bin/ffmpeg',
                    'ffprobe.binaries' => '/opt/homebrew/bin/ffprobe'
                ]);
                $audioFile = $ffmpeg->open($fullLocalPath);
                $mp3Format = new Mp3();
                $convertedPath = str_replace('.oga', '.mp3', $fullLocalPath);
                $audioFile->save($mp3Format, $convertedPath);

                //конвертация аудио в текст
                $speechService = new SpeechToTextService('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');

                $productList = $speechService->convertSpeechToText($convertedPath);

                Log::info('product list: ');
                Log::info(print_r($productList, true));

//                $productList = '
//        Творог - 150грамм;
//        Яблоки - 120грамм;
//        Яйца - 11грамм;
//        Булка - 1202грамм;
//        Хлеб - 43грамм;
//        Майонез - 64грамм;
//        Гренки - 123грамм;
//        Помидоры - 666грамм;
//        Груши - 33грамм;
//        Картошка - 1233грамм;
//        ';

                //получить по апи продукты
                $sendService = new DiaryApiService();

                $responseArray = $sendService->sendText($productList);
                Log::info('response form calories: ');
                Log::info(print_r($responseArray, true));
                //выбрать наиболее релевантный продукт для списка продуктов
                $responsWithProduct = $speechService->chooseTheMostRelevantProduct($responseArray);

                Log::info('relevant product: ');
                Log::info(print_r($responsWithProduct, true));

                //вернуть продукты пользователю в виде сообщения
                if (isset($response['error'])) {
                    $telegram->sendMessage([
                        'chat_id' => $commonData['chatId'],
                        'text' => 'Произошла ошибка: ' . $response['error']
                    ]);
                } else {
                    $messageText = $response['message'] ?? 'No message returned';
                    $telegram->sendMessage([
                        'chat_id' => $commonData['chatId'],
                        'text' => $messageText
                    ]);
                }


            } catch (\Exception $e) {
                Log::error("Error converting audio: " . $e->getMessage());
                $telegram->sendMessage([
                    'chat_id' => $commonData['chatId'],
                    'text' => 'Error processing your audio message.'
                ]);
            }
        } else {
            $text = $message->getText() ?: 'Received non-audio message.';
            $telegram->sendMessage([
                'chat_id' => $commonData['chatId'],
                'text' => $text,
            ]);
        }
    }
}
