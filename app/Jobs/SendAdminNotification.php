<?php

namespace App\Jobs;

use App\Models\Bot;
use App\Models\BotAdmin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;

class SendAdminNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $bot;
    protected $admin;
    protected $message;

    public function __construct(Bot $bot, BotAdmin $admin, $message)
    {
        $this->bot = $bot;
        $this->admin = $admin;
        $this->message = $message;
    }

    public function handle()
    {
        $telegram = new Api($this->bot->token);
        try {
            $telegram->sendMessage([
                'chat_id' => $this->admin->telegram_id,
                'text' => $this->message,
            ]);
            Log::info('Sent message to admin: ' . $this->admin->name);
        } catch (\Exception $e) {
            Log::error('Error sending message to ' . $this->admin->telegram_id . ': ' . $e->getMessage());
        }
    }
}
