<?php

namespace App\Jobs;

use App\Models\Bot;
use App\Models\Manager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;

class SendManagerNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $bot;

    protected $manager;

    protected $message;

    public function __construct(Bot $bot, Manager $manager, $message)
    {
        $this->bot = $bot;
        $this->manager = $manager;
        $this->message = $message;
    }

    public function handle()
    {
        $telegram = new Api($this->bot->token);
        try {
            $telegram->sendMessage([
                'chat_id' => $this->manager->telegram_id,
                'text' => $this->message,
            ]);
            Log::info('Message sent successfully to manager: '.$this->manager->name);
        } catch (\Exception $e) {
            Log::error('Error sending message to '.$this->manager->telegram_id.': '.$e->getMessage());
        }
    }
}
