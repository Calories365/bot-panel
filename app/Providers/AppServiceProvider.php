<?php

namespace App\Providers;

use App\Services\AudioConversionService;
use App\Services\ChatGPTServices\SpeechToTextService;
use App\Services\DiaryApiService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
//        $this->app->singleton(AudioConversionService::class, function ($app) {
//            return new AudioConversionService($app->make(SpeechToTextService::class));
//        });
//
//        $this->app->singleton(DiaryApiService::class, function ($app) {
//            return new DiaryApiService;
//        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping(); // убрать любой обертку, по умолчанию data
        //        JsonResource::wrap('test');//задать глобально обертку

    }
}
