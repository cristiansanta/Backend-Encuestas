<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EnsureApiAuthentication
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
       
        Log::info('EnsureApiAuthentication middleware called', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'has_user' => auth()->guard('sanctum')->check() ? 'yes' : 'no',
            'bearer_token' => $request->bearerToken() ? 'present' : 'absent'
        ]);

        // Check if user is authenticated using Sanctum guard
        if (!auth()->guard('sanctum')->check()) {
            Log::warning('Unauthenticated access attempt', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip()
            ]);
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}