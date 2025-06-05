<?php

namespace App\Http\Controllers;

use App\Models\LanguageSetting;
use App\Services\DiaryApiService;
use Illuminate\Http\Request;

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

        $setting = LanguageSetting::firstOrCreate(
            ['id' => 1],
            ['russian_language_enabled' => $validated['enabled']]
        );

        if ($setting->russian_language_enabled !== $validated['enabled']) {
            $setting->update(['russian_language_enabled' => $validated['enabled']]);
        }

        if (isset($response['error'])) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle Russian language',
                'error' => $response['error'],
            ], 500);
        }

        return response()->json($response);
    }
}
