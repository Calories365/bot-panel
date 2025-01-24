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
        Schema::create('calories_users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->unsignedBigInteger('telegram_id')->nullable();
            $table->boolean('is_banned')->default(false);
            $table->string('phone')->nullable();
            $table->boolean('premium')->default(false);
            $table->boolean('premium_calories')->default(false);
            $table->string('source')->nullable();
            $table->string('email')->nullable();
            $table->string('username_calories')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calories_users');
    }
};
