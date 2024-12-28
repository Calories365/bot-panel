<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('bot_users', function (Blueprint $table) {
            $table->string('locale', 5)->default('en')->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('bot_users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
