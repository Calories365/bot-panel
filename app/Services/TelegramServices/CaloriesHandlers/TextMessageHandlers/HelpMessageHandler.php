<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;

/**
 * /help and the "Как пользоваться" button: how to keep the diary in the bot,
 * with the button that opens the Mini App.
 */
class HelpMessageHandler implements MessageHandlerInterface
{
    public function handle($bot, $telegram, $message, $botUser)
    {
        $guide = $bot->name === 'calories365KNU_bot' ? 'welcome_guide_KNU' : 'welcome_guide';

        $request = [
            'chat_id' => $message->getChat()->getId(),
            'text' => __('calories365-bot.'.$guide, ['open' => __('calories365-bot.open_mini_app')]),
            'disable_web_page_preview' => true,
        ];

        if ($bot->web_app_url) {
            $request['reply_markup'] = json_encode(['inline_keyboard' => [[
                ['text' => __('calories365-bot.open_mini_app'), 'web_app' => ['url' => $bot->web_app_url]],
            ]]]);
        }

        $telegram->sendMessage($request);
    }
}
