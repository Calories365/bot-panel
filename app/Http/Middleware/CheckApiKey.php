<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-Api-Key');

        if ($apiKey !== env('DIARY_API_KEY')) {
            return response()->json(['error' => 'Invalid API key'], 403);
        }

        return $next($request);
    }
}
