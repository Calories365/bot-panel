<?php

namespace App\Providers;

use App\Services\AudioConversionService;
use App\Services\Benchmark\BenchmarkAudioConversionService;
use App\Services\Benchmark\BenchmarkTelegramHandler;
use App\Services\TelegramServices\TelegramHandler;
use Illuminate\Support\ServiceProvider;

class BenchmarkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Replace AudioConversionService with benchmark version (reads local files).
        $this->app->bind(AudioConversionService::class, BenchmarkAudioConversionService::class);

        // Replace TelegramHandler with benchmark version (fake Telegram API → Redis).
        $this->app->singleton(TelegramHandler::class, BenchmarkTelegramHandler::class);
    }
}
