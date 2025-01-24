<?php

namespace App\Jobs;

use App\Utilities\Utilities;
use App\Models\CaloriesUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SaveAndNotifyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $chatId;
    protected $firstName;
    protected $lastName;
    protected $username;
    protected $bot;
    protected $premium;

    /**
     * @param int|string $chatId
     * @param string     $firstName
     * @param string     $lastName
     * @param string     $username
     * @param object     $bot
     * @param bool|int   $premium
     */
    public function __construct($chatId, $firstName, $lastName, $username, $bot, $premium)
    {
        $this->chatId    = $chatId;
        $this->firstName = $firstName;
        $this->lastName  = $lastName;
        $this->username  = $username;
        $this->bot       = $bot;
        $this->premium   = $premium;
    }

    /**
     * Выполнение джобы.
     */
    public function handle()
    {
        $botUser = Utilities::saveAndNotify(
            $this->chatId,
            $this->firstName,
            $this->lastName,
            $this->username,
            $this->bot,
            $this->premium
        );

        $caloriesUser = CaloriesUser::where('telegram_id', $this->chatId)->first();

        if ($caloriesUser) {
            $caloriesUser->name     = $botUser->name;
            $caloriesUser->username = $botUser->username;
            $caloriesUser->phone    = $botUser->phone;
            $caloriesUser->premium  = $botUser->premium;

            $caloriesUser->save();
        }

    }
}
