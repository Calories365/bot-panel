<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
            'calories_id'            => $payload['calories_id'],
        ];

        $newUser = CaloriesUser::create($dataForInsert);

        return response()->json(['status' => 'ok', 'created_id' => $newUser->id], 201);
    }
}
