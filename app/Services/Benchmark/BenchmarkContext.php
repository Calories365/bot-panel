<?php

namespace App\Services\Benchmark;

use Illuminate\Support\Facades\Redis;

/**
 * Static context shared within a single Horizon worker process during benchmark execution.
 * Stores the current request_id so that SpeechToTextService can record timing data.
 */
class BenchmarkContext
{
    public static ?string $currentRequestId = null;

    public static function recordTiming(string $key, float $valueMs): void
    {
        if (self::$currentRequestId) {
            Redis::hset('benchmark:timing:'.self::$currentRequestId, $key, (string) round($valueMs, 2));
        }
    }

    public static function recordData(string $key, string $value): void
    {
        if (self::$currentRequestId) {
            Redis::hset('benchmark:timing:'.self::$currentRequestId, $key, $value);
        }
    }

    public static function reset(): void
    {
        self::$currentRequestId = null;
    }
}
