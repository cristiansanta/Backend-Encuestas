<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
    {
        public function handle(Request $request, Closure $next)
        {
            $apiKey = $request->header('x-api-key');
           
            if ($apiKey !== config('app.api_key')) {

                return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }
            
            return $next($request);
        }
    }    