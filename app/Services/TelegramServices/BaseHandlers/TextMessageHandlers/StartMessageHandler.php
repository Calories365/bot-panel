<?php

namespace App\Services\TelegramServices\BaseHandlers\TextMessageHandlers;

use App\Models\BotUser;
use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;
use App\Traits\BasicDataExtractor;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\FileUpload\InputFile;

class StartMessageHandler implements MessageHandlerInterface
{
    use BasicDataExtractor;

    public function handle($bot, $telegram, $message, $botUser)
    {
        $text = $message->getText();

        if (str_contains($text, 'start')) {
            $commonData = self::extractCommonData($message);
            $imagePath = $bot->message_image;
            $messageText = $bot->message;

            if ($imagePath) {
                $storagePath = parse_url($imagePath, PHP_URL_PATH);

                if ($storagePath) {
                    $relativeImagePath = ltrim($storagePath, '/');
                    $relativeImagePath = preg_replace('#^storage/#', 'public/', $relativeImagePath);

                    if (Storage::exists($relativeImagePath)) {
                        $absoluteImagePath = Storage::path($relativeImagePath);
                        $photo = InputFile::create($absoluteImagePath, basename($absoluteImagePath));

                        try {
                            $telegram->sendPhoto([
                                'chat_id' => $commonData['chatId'],
                                'photo'   => $photo,
                                'caption' => $messageText,
                            ]);
                        } catch (\Telegram\Bot\Exceptions\TelegramOtherException $e) {
                            if ($e->getMessage() === 'Forbidden: bot was blocked by the user') {
                                $userModel = BotUser::where('telegram_id', $commonData['chatId'])->firstOrFail();
                                $userModel->is_banned = true;
                                $userModel->save();
                            } else {
                                Log::info($e->getMessage());
                            }
                        } catch (\Exception $e) {
                            Log::error('Error sending photo: '.$e->getMessage());
                        }
                    } else {
                        Log::error('Image file not found: '.$relativeImagePath);
                    }
                } else {
                    Log::error('Invalid image path provided: '.$imagePath);
                }
            } else {
                $telegram->sendMessage([
                    'chat_id' => $commonData['chatId'],
                    'text'    => $messageText,
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
