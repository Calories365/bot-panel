<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditingProcessCallbackQuery;

use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\CallbackQueryHandlerInterface;
use App\Services\TelegramServices\CaloriesHandlers\EditHandlerTrait;
use Illuminate\Support\Facades\Cache;

class EditingBaseCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    use EditHandlerTrait;
    public bool $blockAble = false;
    protected $callbackData;
    protected $userId;
    protected $chatId;
    protected $messageId;
    protected $productId;
    protected $userProducts;
    protected $editingState;

    public function handle($bot, $telegram, $callbackQuery, $botUser)
    {
        if (!$this->initialize($telegram, $callbackQuery, $botUser)) {
            return;
        }

        $this->process($bot, $telegram, $callbackQuery, $botUser);
    }

    protected function initialize($telegram, $callbackQuery, $botUser)
    {
        $this->callbackData = $callbackQuery->getData();
        $this->userId = $callbackQuery->getFrom()->getId();
        $this->chatId = $callbackQuery->getMessage()->getChat()->getId();
        $this->messageId = $callbackQuery->getMessage()->getMessageId();

        $this->editingState = Cache::get("user_editing_{$this->userId}");

        if (!$this->editingState) {
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text'       => __('calories365-bot.editing_session_expired'),
                'show_alert' => true,
            ]);
            return false;
        }

        $this->productId = $this->editingState['product_id'];

        $this->userProducts = Cache::get("user_products_{$this->userId}");

        if (!$this->userProducts || !isset($this->userProducts[$this->productId])) {
            $this->clearEditingState($this->userId);
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text'       => __('calories365-bot.product_not_found'),
                'show_alert' => true,
            ]);
            return false;
        }

        return true;
    }
}
