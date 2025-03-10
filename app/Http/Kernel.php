<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * Estos middleware se ejecutan en cada solicitud a tu aplicación.
     *
     * @var array
     */

     protected $middleware =[
        \Illuminate\Http\Middleware\HandleCors::class,
        
        
     ];
    protected $middlewareGroups = [
      // ...
      'api' => [
          \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
          'throttle:api',
          \Illuminate\Routing\Middleware\SubstituteBindings::class,
          \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
          \Illuminate\Session\Middleware\StartSession::class,
          \Illuminate\View\Middleware\ShareErrorsFromSession::class,
          \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
          \Illuminate\Routing\Middleware\SubstituteBindings::class,
          \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
         
      ],
  ];
    
     protected $routeMiddleware = [
        'api.key' => \App\Http\Middleware\ApiKeyMiddleware::class,
        "cors" => \App\Http\Middleware\Cors::class,

     ];


    
}