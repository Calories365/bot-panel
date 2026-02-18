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

    public static function accumulateTiming(string $key, float $valueMs): void
    {
        if (self::$currentRequestId) {
            $redisKey = 'benchmark:timing:'.self::$currentRequestId;
            $current = (float) Redis::hget($redisKey, $key);
            Redis::hset($redisKey, $key, (string) round($current + $valueMs, 2));
        }
    }

    public static function recordData(string $key, string $value): void
    {
        if (self::$currentRequestId) {
            Redis::hset('benchmark:timing:'.self::$currentRequestId, $key, $value);
        }
    }

    public static function appendData(string $key, string $value, string $separator = ' | '): void
    {
        if (self::$currentRequestId) {
            $redisKey = 'benchmark:timing:'.self::$currentRequestId;
            $current = Redis::hget($redisKey, $key);
            $new = $current ? $current.$separator.$value : $value;
            Redis::hset($redisKey, $key, $new);
        }
    }

    public static function reset(): void
    {
        self::$currentRequestId = null;
    }
}
