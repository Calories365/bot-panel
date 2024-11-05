<?php

namespace App\Services\TelegramServices\BaseHandlers\UpdateHandlers;

use App\Models\BotUser;

class MyChatMemberUpdateHandler implements UpdateHandlerInterface
{

    public function handle($bot, $telegram, $update)
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
}
