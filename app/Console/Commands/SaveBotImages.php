<?php

namespace App\Console\Commands;

use App\Models\Bot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SaveBotImages extends Command
{
    protected $signature = 'bots:save-images';

    protected $description = 'Saves images from bots to the transfer directory with their original extensions.';

    public function handle()
    {
        $bots = Bot::all();
        foreach ($bots as $bot) {
            $sourcePathJpg = 'public/transfer/'.$bot->name.'.jpg';
            $sourcePathPng = 'public/transfer/'.$bot->name.'.png';
            $sourcePath = Storage::exists($sourcePathJpg) ? $sourcePathJpg : (Storage::exists($sourcePathPng) ? $sourcePathPng : null);

            if ($sourcePath) {
                $fileContents = Storage::get($sourcePath);

                $uploadedImageUrl = $this->uploadImageToBot($fileContents, $sourcePath);

                if ($uploadedImageUrl) {
                    $bot->update(['message_image' => $uploadedImageUrl]);
                    $this->info("Updated image for {$bot->name} with new URL: {$uploadedImageUrl}");
                } else {
                    $this->error("Failed to upload image for {$bot->name}");
                }
            } else {
                $this->error("File does not exist for {$bot->name} in both JPG and PNG formats");
            }
        }
    }

    protected function uploadImageToBot($fileContents, $sourcePath)
    {
        $targetPath = 'public/bots/'.basename($sourcePath);
        Storage::put($targetPath, $fileContents);

        return Storage::url($targetPath);
    }
}
