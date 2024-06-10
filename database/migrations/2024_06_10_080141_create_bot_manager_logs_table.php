<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('bot_manager_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->unsignedBigInteger('manager_id');
            $table->timestamps();

            $table->foreign('bot_id')->references('id')->on('bots')
                ->onDelete('cascade');
            $table->foreign('manager_id')->references('id')->on('managers')
                ->onDelete('cascade');

            $table->unique(['bot_id'], 'unique_bot_manager_logs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('bot_manager_logs');
    }
};
