<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The 1C helper bot: gathers the questions about the 1C base forwarded to it
     * and gives them to the worker on the 1C server. The name is the key of
     * OnecHelperService in TelegramHandler.
     */
    public function up(): void
    {
        if (DB::table('bot_types')->where('name', 'OnecHelper')->exists()) {
            return;
        }

        DB::table('bot_types')->insert([
            'name' => 'OnecHelper',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('bot_types')->where('name', 'OnecHelper')->delete();
    }
};
