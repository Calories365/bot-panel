<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditingProcessCallbackQuery;

use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\CallbackQueryHandlerInterface;
use App\Services\TelegramServices\CaloriesHandlers\EditHandlerTrait;
use Illuminate\Support\Facades\Cache;

class EditingBaseCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    use EditHandlerTrait;

    protected $callbackData;
    protected $userId;
    protected $chatId;
    protected $messageId;
    protected $productId;
    protected $userProducts;
    protected $editingState;

    public function handle($bot, $telegram, $callbackQuery)
    {
        if (!$this->initialize($telegram, $callbackQuery)) {
            return;
        }

        $this->process($bot, $telegram, $callbackQuery);
    }

    protected function initialize($telegram, $callbackQuery)
    {
        $this->callbackData = $callbackQuery->getData();
        $this->userId = $callbackQuery->getFrom()->getId();
        $this->chatId = $callbackQuery->getMessage()->getChat()->getId();
        $this->messageId = $callbackQuery->getMessage()->getMessageId();

        $this->editingState = Cache::get("user_editing_{$this->userId}");

        if (!$this->editingState) {
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text' => 'Сессия редактирования истекла или отсутствует.',
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
                'text' => 'Продукт не найден или истекло время сессии.',
                'show_alert' => true,
            ]);
            return false;
        }

        return true;
    }
}
