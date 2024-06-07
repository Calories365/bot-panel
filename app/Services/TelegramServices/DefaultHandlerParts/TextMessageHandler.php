<?php

namespace App\Services\TelegramServices\DefaultHandlerParts;

use App\Models\BotUser;
use App\Traits\BasicDataExtractor;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\FileUpload\InputFile;

class TextMessageHandler
{
    use BasicDataExtractor;

    public static function handle($bot, $telegram, $update)
    {
        $message = $update->getMessage();
        $text = $message->getText();

        if (str_contains($text, 'start')) {
            $commonData = self::extractCommonData($message);
            $imagePath = $bot->message_image;
            $messageText = $bot->message;

            if ($imagePath) {
                $absoluteImagePath = Storage::path($imagePath);
                $photo = InputFile::create($absoluteImagePath, basename($absoluteImagePath));

                try {
                    $telegram->sendPhoto([
                        'chat_id' => $commonData['chatId'],
                        'photo' => $photo,
                        'caption' => $messageText
                    ]);
                } catch (Telegram\Bot\Exceptions\TelegramOtherException $e) {
                    if ($e->getMessage() === 'Forbidden: bot was blocked by the user') {
                        $userModel = BotUser::where('telegram_id', $commonData['chatId'])->firstOrFail();
                        $userModel->is_banned = 1;
                        $userModel->save();
                    } else {
                        Log::info($e->getMessage());
                    }
                }
            } else {
                $telegram->sendMessage([
                    'chat_id' => $commonData['chatId'],
                    'text' => $messageText,
                ]);
            }

            Utilities::saveAndNotify(
                $commonData['chatId'],
                $commonData['firstName'],
                $commonData['lastName'],
                $commonData['username'],
                $bot,
                $commonData['premium']
            );
        }
    }
}
