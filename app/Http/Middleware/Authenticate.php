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
        // For API requests, always return null to avoid redirect
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        // For web routes, you might want to define a login route or return null
        return null;
    }
}
