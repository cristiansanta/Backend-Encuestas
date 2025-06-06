# Autenticación

- [Configuración](#configuration)
- [Autenticación Web](#web-auth)
- [Autenticación API](#api-auth)
- [Middleware](#middleware)
- [Políticas de Acceso](#policies)

<a name="configuration"></a>
## Configuración

El sistema utiliza dos métodos de autenticación:
- Laravel Breeze para la autenticación web
- Laravel Sanctum para la autenticación API

Archivo de configuración principal:

```php
// config/auth.php
return [
    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],
    ],
];
```

<a name="web-auth"></a>
## Autenticación Web

### Rutas de Autenticación
```php
// routes/auth.php
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
});
```

### Protección de Rutas
```php
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');
    Route::resource('surveys', SurveyController::class);
});
```

<a name="api-auth"></a>
## Autenticación API

### Generación de Token
```php
// app/Http/Controllers/Auth/ApiAuthController.php
public function login(Request $request)
{
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required'
    ]);

    if (!Auth::attempt($credentials)) {
        return response()->json([
            'message' => 'Credenciales inválidas'
        ], 401);
    }

    $user = Auth::user();
    $token = $user->createToken('api-token')->plainTextToken;

    return response()->json([
        'token' => $token,
        'type' => 'Bearer'
    ]);
}
```

<a name="middleware"></a>
## Middleware

### Protección API
```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('surveys', Api\SurveyController::class);
    Route::apiResource('questions', Api\QuestionController::class);
    Route::post('surveys/{survey}/responses', [Api\ResponseController::class, 'store']);
});
```

<a name="policies"></a>
## Políticas de Acceso

### Política de Encuestas
```php
// app/Policies/SurveyPolicy.php
class SurveyPolicy
{
    public function view(User $user, Survey $survey)
    {
        return $user->id === $survey->user_id;
    }

    public function update(User $user, Survey $survey)
    {
        return $user->id === $survey->user_id;
    }

    public function delete(User $user, Survey $survey)
    {
        return $user->id === $survey->user_id;
    }
}
```

### Registro de Políticas
```php
// app/Providers/AuthServiceProvider.php
protected $policies = [
    Survey::class => SurveyPolicy::class,
];
```