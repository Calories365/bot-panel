<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->string('video_ru')->nullable()->after('message_image');
            $table->string('video_ua')->nullable()->after('video_ru');
            $table->string('video_eng')->nullable()->after('video_ua');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->dropColumn(['video_ru', 'video_ua', 'video_eng']);
        });
    }
};
