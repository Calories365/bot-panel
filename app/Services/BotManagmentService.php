<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BotManagmentService
{
    public function handleFileUpload($request, string $fieldName): ?string
    {
        if (!$request->hasFile($fieldName)) {
            return null;
        }
        $file = $request->file($fieldName);
        $filePath = $file->store('public/bots');
        $url = Storage::url($filePath);
        return str_replace('/storage/bots', '/images', $url);
    }



    public function syncManagers($request, $bot): void
    {
        $managerIdsJson = $request->input('managers');
        $managerIds = $managerIdsJson ? json_decode($managerIdsJson, true) : null;
        if (is_array($managerIds)) {
            $managerIds = array_column($managerIds, 'id');
        } else {
            $managerIds = [];
        }
        $bot->managers()->sync($managerIds);
    }
}
