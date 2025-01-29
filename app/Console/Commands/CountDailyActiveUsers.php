<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BotUser;
use Carbon\Carbon;
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
    protected $description = 'Подсчитывает количество активных пользователей за текущий день и записывает в таблицу daily_activity';

    /**
     * Execute the console command.
     */
    public function handle()
    {
//        $today = Carbon::today();
        $today = Carbon::yesterday();
        $count = BotUser::where('last_active_at', '>=', $today)->count();

        DB::table('daily_activity')->updateOrInsert(
            ['date' => $today->format('Y-m-d')],
            [
                'count'      => $count,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->info("Записали в daily_activity: дата = {$today->format('Y-m-d')}, count = {$count}");
    }
}
