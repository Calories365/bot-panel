<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function download($filename): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        $filePath = 'public/exports/'.$filename;

        if (Storage::disk('public')->exists($filePath)) {
            $content = Storage::disk('public')->get($filePath);

            return Response::download(storage_path('app/'.$filePath));
        } else {
            Log::error("File $filename did not find.");

            return response()->json(['message' => "File $filename did not find."], 404);
        }
    }
}
