<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::rename('bot_user_bot', 'bot_user_bots');
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::rename('bot_user_bots', 'bot_user_bot');
    }
};
