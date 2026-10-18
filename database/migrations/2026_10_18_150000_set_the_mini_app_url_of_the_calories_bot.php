<?php

use App\Models\Bot;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Calories365_bot had no web_app_url, so it showed neither the menu button nor
     * the "open the app" button of the Mini App, and its users could not reach the
     * diary from Telegram. The Mini App is the diary itself. The menu button is set
     * through Telegram only in production: local databases hold the production
     * tokens, and a local migration would change the live bot.
     */
    public function up(): void
    {
        $bot = Bot::query()->where('name', 'Calories365_bot')->whereNull('web_app_url')->first();

        if (! $bot) {
            return;
        }

        $bot->forceFill(['web_app_url' => 'https://calculator.calories365.com'])->save();

        if (app()->environment('production')) {
            $bot->updateMenuButton();
        }
    }

    public function down(): void
    {
        //
    }
};
