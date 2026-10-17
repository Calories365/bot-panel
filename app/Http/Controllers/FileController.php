<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    /**
     * Download an export from the private disk; the route requires a login.
     */
    public function download($filename): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $filePath = 'exports/'.basename((string) $filename);

        if (Storage::disk('local')->exists($filePath)) {
            return Storage::disk('local')->download($filePath);
        }

        Log::error("File $filename did not find.");

        return response()->json(['message' => "File $filename did not find."], 404);
    }
}
