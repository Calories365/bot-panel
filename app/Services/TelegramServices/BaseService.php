<?php

namespace App\Services\TelegramServices;

use App\Interfaces\BotHandlerStrategy;
use App\Models\BotUser;
use Illuminate\Support\Facades\Log;

class BaseService implements BotHandlerStrategy
{
    public static function getUpdateType($bot, $telegram, $update): void
    {
        $updateType = $update->detectType();
        switch ($updateType) {
            case 'message':
                static::handleMessage($bot, $telegram, $update);
                break;
            case 'my_chat_member':
                static::handleMyChatMember($bot, $telegram, $update);
                break;
            default:
                Log::info("Unhandled update type: " . $updateType);
                break;
        }
    }

    /**
     * @throws \Exception
     */
    public static function getMessageType($bot, $telegram, $update): void
    {
        $message = $update->getMessage();

        switch (true) {
            case isset($message['contact']):
                static::handleContactMessage($bot, $telegram, $update);
                break;

            case isset($message['text']):
                static::handleTextMessage($bot, $telegram, $update);
                break;

            default:
                Log::info("Unknown message type: " . json_encode($message));
                break;
        }
    }

    public static function handleMyChatMember($bot, $telegram, $update): void
    {
        $myChatMember = $update->getMyChatMember();
        $newStatus = $myChatMember['new_chat_member']['status'];
        $userId = $myChatMember['from']['id'];


        $userModel = BotUser::where('telegram_id', $userId)->first();

        if ($userModel) {
            if ($newStatus === 'kicked') {
                $userModel->banned_bots()->syncWithoutDetaching([$bot->id]);
            } else {
                $userModel->banned_bots()->detach($bot->id);
            }
        }
    }


    public static function handleMessage($bot, $telegram, $update)
    {
    }

    public
    static function handleContactMessage($bot, $telegram, $update)
    {
    }

    public static function handleTextMessage($bot, $telegram, $update)
    {
    }
}
