<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ManagersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('managers')->insert([
            ['name' => 'Kub14ka', 'telegram_id' => '385493515', 'is_last' => false],
            ['name' => 'ZOV_AZOVA', 'telegram_id' => '1418480351', 'is_last' => false],
            ['name' => 'maxim_lrvl', 'telegram_id' => '6992535087', 'is_last' => true],
        ]);
    }
}
