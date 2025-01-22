<?php

namespace App\Jobs;

use App\Models\BotUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateLastActiveAtJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * ID пользователя, которого нужно обновить
     */
    private int $botUserId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $botUserId)
    {
        $this->botUserId = $botUserId;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $botUser = BotUser::find($this->botUserId);

        if ($botUser && $botUser->calories_id) {
            $botUser->update(['last_active_at' => now()]);
        }
    }
}
