<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\CaloriesUser;

class SyncController extends Controller
{
    public function storeNewUser(Request $request)
    {
        $payload = $request->input('payload', []);

        $dataForInsert = [
            'email'             => $payload['email'] ?? null,
            'username_calories' => $payload['name']  ?? null,
            'source'            => 'calories_only',
            'calories_id'       => $payload['calories_id'],
        ];

        $newUser = CaloriesUser::create($dataForInsert);

        return response()->json(['status' => 'ok', 'created_id' => $newUser->id], 201);
    }
    public function updatePremiumStatus(Request $request)
    {
        Log::info('start: ');
        if (!$request->has('payload.calories_id')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing calories_id in payload'
            ], 400);
        }

        $caloriesId   = $request->input('payload.calories_id');
        $premiumUntil = $request->input('payload.premium_until');

        try {
            Log::info('data: ');
            Log::info(print_r($caloriesId, true));
            Log::info(print_r($premiumUntil, true));
            DB::transaction(function () use ($caloriesId, $premiumUntil) {
                $updated = CaloriesUser::where('calories_id', $caloriesId)
                    ->update(['premium_calories' => true]);

                if (!$updated) {
                    throw new \Exception('User not found');
                }

                Subscription::updateOrCreate(
                    ['user_id' => $caloriesId],
                    ['premium_until' => $premiumUntil]
                );
            });

            return response()->json(['status' => 'ok'], 200);

        } catch (\Exception $e) {
            Log::error("Premium update error: " . $e->getMessage());

            if ($e->getMessage() === 'User not found') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found'
                ], 404);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error'
            ], 500);
        }
    }

}
