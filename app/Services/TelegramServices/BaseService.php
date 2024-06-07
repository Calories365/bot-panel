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
                Log::info('BaseService::getMessageType::$message[text]');
                static::handleTextMessage($bot, $telegram, $update);
                break;

            default:
                throw new \Exception("Unknown message type");
        }
    }

    public static function handleMyChatMember($bot, $telegram, $update): void
    {
        $myChatMember = $update->getMyChatMember();
        $newStatus = $myChatMember['new_chat_member']['status'];

        if ($newStatus === 'kicked') {

            $userId = $myChatMember['from']['id'];

            Log::info("Bot was kicked or blocked by user: " . $userId);

            $userModel = BotUser::where('telegram_id', $userId)->firstOrFail();

            $userModel->banned_bots()->syncWithoutDetaching([$bot->id]);
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
