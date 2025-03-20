# Gestión de Encuestas

- [Modelo Survey](#survey-model)
- [Controlador](#controller)
- [Rutas](#routes)
- [Validación](#validation)
- [Ejemplos de Uso](#usage)

<a name="survey-model"></a>
## Modelo Survey

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Survey extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'starts_at',
        'ends_at',
        'user_id'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime'
    ];

    public function isActive(): bool
    {
        return $this->starts_at <= now() && 
               ($this->ends_at === null || $this->ends_at >= now());
    }
}
```

<a name="controller"></a>
## Controlador

```php
namespace App\Http\Controllers;

use App\Models\Survey;
use App\Http\Requests\StoreSurveyRequest;
use App\Services\SurveyService;

class SurveyController extends Controller
{
    public function store(StoreSurveyRequest $request, SurveyService $service)
    {
        $survey = $service->createSurvey($request->validated());
        return response()->json($survey, 201);
    }
}
```

<a name="routes"></a>
## Rutas API

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('surveys', SurveyController::class);
    Route::post('surveys/{survey}/publish', [SurveyController::class, 'publish']);
});
```

<a name="validation"></a>
## Validación

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSurveyRequest extends FormRequest
{
    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'nullable|date|after_or_equal:today',
            'ends_at' => 'nullable|date|after:starts_at',
            'questions' => 'required|array|min:1',
            'questions.*.text' => 'required|string|max:500',
            'questions.*.type' => 'required|in:multiple_choice,text,rating'
        ];
    }
}
```

<a name="usage"></a>
## Ejemplos de Uso

### Crear una Encuesta
```php
$survey = Survey::create([
    'title' => 'Satisfacción del Cliente',
    'description' => 'Encuesta mensual de satisfacción',
    'starts_at' => now(),
    'ends_at' => now()->addDays(30)
]);
```

### Consultar Encuestas Activas
```php
$activeSurveys = Survey::query()
    ->where('starts_at', '<=', now())
    ->where(function ($query) {
        $query->whereNull('ends_at')
              ->orWhere('ends_at', '>=', now());
    })
    ->get();
```