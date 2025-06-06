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
        Schema::rename('manager_bot', 'bot_manager');
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::rename('bot_manager', 'manager_bot');
    }
};
