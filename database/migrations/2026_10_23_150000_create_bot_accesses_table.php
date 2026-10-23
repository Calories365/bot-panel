<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who sent the password of a bot that asks for one. By telegram_id and not
     * in bot_user_bots: BotUser::addOrUpdateUser recreates that row on every
     * /start.
     */
    public function up(): void
    {
        Schema::create('bot_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('telegram_id');
            $table->timestamps();

            $table->unique(['bot_id', 'telegram_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_accesses');
    }
};
