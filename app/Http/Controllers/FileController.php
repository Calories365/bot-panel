<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
class FileController extends Controller
{
    public function download($filename): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        $filePath = 'public/exports/' . $filename;

        if (Storage::disk('public')->exists($filePath)) {
            $content = Storage::disk('public')->get($filePath);
            Log::info("Содержимое файла $filename:\n" . $content);

            return Response::download(storage_path('app/' . $filePath));
        } else {
            Log::error("Файл $filename не найден.");
            return response()->json(['message' => "Файл $filename не найден."], 404);
        }
    }
}
