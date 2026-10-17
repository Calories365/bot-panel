<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // The route named "login" is the SPA catch-all /{any}; route('login') without
        // the parameter throws, so a guest got a 500 instead of the login page.
        return $request->expectsJson() ? null : url('/login');
    }
}
