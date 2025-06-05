<?php

namespace App\Console\Commands;

use App\Models\BotUser;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CountDailyActiveUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'daily:count-active-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Counts the number of active users for the current day and writes it to the daily_activity table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();
        $count = BotUser::where('last_active_at', '>=', $today)->count();

        DB::table('daily_activity')->updateOrInsert(
            ['date' => $today->format('Y-m-d')],
            [
                'count' => $count,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->info("Wrote in daily_activity: data = {$today->format('Y-m-d')}, count = {$count}");
    }
}
