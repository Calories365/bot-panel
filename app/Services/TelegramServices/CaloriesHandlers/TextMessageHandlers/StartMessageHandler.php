<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Models\BotUser;
use App\Services\TelegramServices\BaseHandlers\TextMessageHandlers\Telegram;
use App\Traits\BasicDataExtractor;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Keyboard\Keyboard;

class StartMessageHandler
{

    use BasicDataExtractor;

    public function handle($bot, $telegram, $message)
    {
        $text = $message->getText();

        Log::info('calories');
        if (str_contains($text, 'start')) {
            $commonData = self::extractCommonData($message);
            $imagePath = $bot->message_image;
            $messageText = $bot->message;

            $keyboard = Keyboard::make([
                'resize_keyboard' => true,
            ])->row([
                [
                    'text' => '/stats_morning'
                ],
                [
                    'text' => '/stats_dinner'
                ]
            ])->row([
                [
                    'text' => '/stats_supper'
                ],
                [
                    'text' => '/stats'
                ]
            ]);


            if ($imagePath) {
                $relativeImagePath = str_replace('/images', 'public/bots', parse_url($imagePath, PHP_URL_PATH));

                if (Storage::exists($relativeImagePath)) {
                    $absoluteImagePath = Storage::path($relativeImagePath);
                    $photo = InputFile::create($absoluteImagePath, basename($absoluteImagePath));

                    try {
                        $telegram->sendPhoto([
                            'chat_id' => $commonData['chatId'],
                            'photo' => $photo,
                            'caption' => $messageText,
                            'reply_markup' => $keyboard
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
                    Log::error("Image file not found: " . $relativeImagePath);
                }
            } else {
                $telegram->sendMessage([
                    'chat_id' => $commonData['chatId'],
                    'text' => $messageText,
                    'reply_markup' => $keyboard
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
