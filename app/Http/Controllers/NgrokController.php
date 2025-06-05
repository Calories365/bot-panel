<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NgrokController extends Controller
{
    /**
     * Update the webhook URL for the calories bot
     */
    public function updateWebhook(Request $request): JsonResponse
    {
        $request->validate([
            'ngrok_url' => 'required|url',
        ]);

        $ngrokUrl = $request->input('ngrok_url');

        $bot = Bot::where('type_id', 6)->first();

        if (! $bot) {
            Log::error('Calories bot (type_id=6) not found');

            return response()->json([
                'success' => false,
                'message' => 'Calories bot not found',
            ], 404);
        }

        $bot->web_hook = $ngrokUrl;
        $bot->save();

        $success = $bot->updateWeebHook();

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Webhook updated successfully' : 'Failed to update webhook at Telegram API',
            'bot' => $bot->name,
            'webhook_url' => $ngrokUrl,
        ]);
    }
}
