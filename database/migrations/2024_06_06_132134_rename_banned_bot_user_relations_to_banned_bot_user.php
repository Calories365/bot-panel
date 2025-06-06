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
        Schema::rename('banned_bot_user_relations', 'banned_bot_user');
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::rename('banned_bot_user', 'banned_bot_user_relations');
    }
};
