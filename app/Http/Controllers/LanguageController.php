<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DiaryApiService;

class LanguageController extends Controller
{
    private DiaryApiService $diaryApiService;

    public function __construct(DiaryApiService $diaryApiService)
    {
        $this->diaryApiService = $diaryApiService;
    }

    public function toggleRussianLanguage(Request $request)
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $response = $this->diaryApiService->toggleRussianLanguage($validated['enabled']);

        if (isset($response['error'])) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle Russian language',
                'error' => $response['error']
            ], 500);
        }

        return response()->json($response);
    }
} 