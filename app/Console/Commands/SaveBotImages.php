<?php

namespace App\Console\Commands;

use App\Models\Bot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SaveBotImages extends Command
{
    protected $signature = 'bots:save-images';
    protected $description = 'Saves images from bots to the transfer directory with their original extensions.';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $bots = Bot::all();
        foreach ($bots as $bot) {
            $imagePath = $bot->message_image;
            if ($imagePath) {
                $absoluteImagePath = Storage::path($imagePath);

                if (Storage::exists($imagePath)) {
                    $fileContents = Storage::get($imagePath);
                    $fileExtension = pathinfo($absoluteImagePath, PATHINFO_EXTENSION);
                    $fileName = $bot->name . '.' . $fileExtension;
                    $targetPath = 'public/transfer/' . $fileName;

                    Storage::put($targetPath, $fileContents);

                    $url = Storage::url($targetPath);
                    $this->info("Saved image for {$bot->name} with original extension: {$url}");
                } else {
                    $this->error("File does not exist: {$imagePath}");
                }
            }
        }
    }


}
