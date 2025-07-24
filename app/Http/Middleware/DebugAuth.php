<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class DebugAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Log información detallada sobre la petición de autenticación
        Log::info('=== DEBUG AUTH MIDDLEWARE ===', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'headers' => [
                'authorization' => $request->header('Authorization'),
                'accept' => $request->header('Accept'),
                'content-type' => $request->header('Content-Type'),
                'user-agent' => $request->header('User-Agent'),
            ],
            'bearer_token' => $request->bearerToken(),
            'all_headers' => $request->headers->all(),
        ]);

        // Verificar diferentes guards
        Log::info('=== AUTH GUARDS CHECK ===', [
            'default_guard' => config('auth.defaults.guard'),
            'sanctum_check' => Auth::guard('sanctum')->check(),
            'web_check' => Auth::guard('web')->check(),
            'api_check' => Auth::guard('api')->check(),
            'sanctum_user' => Auth::guard('sanctum')->user() ? Auth::guard('sanctum')->user()->id : null,
        ]);

        // Verificar token en base de datos si existe
        if ($request->bearerToken()) {
            $tokenExists = \Laravel\Sanctum\PersonalAccessToken::findToken($request->bearerToken());
            Log::info('=== TOKEN DATABASE CHECK ===', [
                'token_found_in_db' => $tokenExists ? 'YES' : 'NO',
                'token_user_id' => $tokenExists ? $tokenExists->tokenable_id : null,
                'token_name' => $tokenExists ? $tokenExists->name : null,
                'token_abilities' => $tokenExists ? $tokenExists->abilities : null,
            ]);
        }

        return $next($request);
    }
}