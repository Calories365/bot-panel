<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A bot that answers a voice message with its text. The name is the key of
     * TranscriptionService in TelegramHandler.
     */
    public function up(): void
    {
        if (DB::table('bot_types')->where('name', 'Transcription')->exists()) {
            return;
        }

        DB::table('bot_types')->insert([
            'name' => 'Transcription',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('bot_types')->where('name', 'Transcription')->delete();
    }
};
