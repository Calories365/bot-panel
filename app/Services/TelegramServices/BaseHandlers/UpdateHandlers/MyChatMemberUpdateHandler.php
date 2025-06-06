<?php

namespace App\Services\TelegramServices\BaseHandlers\UpdateHandlers;

class MyChatMemberUpdateHandler implements UpdateHandlerInterface
{
    public function handle($bot, $telegram, $update, $botUser)
    {
        $myChatMember = $update->getMyChatMember();
        $newStatus = $myChatMember['new_chat_member']['status'];
        $userId = $myChatMember['from']['id'];

        $userModel = $botUser;

        if ($userModel) {
            if ($newStatus === 'kicked') {
                $userModel->banned_bots()->syncWithoutDetaching([$bot->id]);
            } else {
                $userModel->banned_bots()->detach($bot->id);
            }
        }
    }
}
