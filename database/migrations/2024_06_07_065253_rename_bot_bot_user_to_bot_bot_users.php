<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::rename('bot_bot_user', 'bot_bot_users');
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::rename('bot_bot_users', 'bot_bot_user');
    }
};
