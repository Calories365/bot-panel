<?php

namespace App\Http\Controllers;

use App\Models\CaloriesUser;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    public function storeNewUser(Request $request): JsonResponse
    {
        $payload = $request->input('payload', []);

        // A Mini App account is already here: TelegramWebAppController::link stores
        // it before this queued report arrives. Fill in what the diary knows instead
        // of failing on the unique calories_id.
        $user = CaloriesUser::firstOrNew(['calories_id' => $payload['calories_id']]);

        $user->email = $payload['email'] ?? $user->email;
        $user->username_calories = $payload['name'] ?? $user->username_calories;
        $user->source = $user->source ?: 'calories_only';
        $user->save();

        return response()->json(['status' => 'ok', 'created_id' => $user->id], $user->wasRecentlyCreated ? 201 : 200);
    }

    public function updatePremiumStatus(Request $request): JsonResponse
    {
        if (! $request->has('payload.calories_id')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing calories_id in payload',
            ], 400);
        }

        $caloriesId = $request->input('payload.calories_id');
        $premiumUntil = $request->input('payload.premium_until');

        try {
            DB::transaction(function () use ($caloriesId, $premiumUntil) {
                $updated = CaloriesUser::where('calories_id', $caloriesId)
                    ->update(['premium_calories' => true]);

                if (! $updated) {
                    throw new \Exception('User not found');
                }

                Subscription::updateOrCreate(
                    ['user_id' => $caloriesId],
                    ['premium_until' => $premiumUntil]
                );
            });

            return response()->json(['status' => 'ok'], 200);

        } catch (\Exception $e) {
            Log::error('Premium update error: '.$e->getMessage());

            if ($e->getMessage() === 'User not found') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                ], 404);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }
}
