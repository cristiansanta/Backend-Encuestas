# Gestión de Respuestas

- [Modelo Response](#response-model)
- [Controlador](#controller)
- [Validación](#validation)
- [Ejemplos de Uso](#usage)

<a name="response-model"></a>
## Modelo Response

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Response extends Model
{
    protected $fillable = [
        'question_id',
        'user_id',
        'answer_value',
        'answer_text',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'json',
        'answer_value' => 'integer'
    ];

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
```

<a name="controller"></a>
## Controlador

```php
namespace App\Http\Controllers;

use App\Models\Response;
use App\Http\Requests\StoreResponseRequest;
use App\Services\ResponseService;

class ResponseController extends Controller
{
    public function store(StoreResponseRequest $request, ResponseService $service)
    {
        $responses = $service->createResponses($request->validated());
        return response()->json(['message' => 'Responses saved successfully']);
    }
}
```

<a name="validation"></a>
## Validación de Respuestas

```php
namespace App\Http\Requests;

class StoreResponseRequest extends FormRequest
{
    public function rules()
    {
        return [
            'survey_id' => 'required|exists:surveys,id',
            'responses' => 'required|array',
            'responses.*.question_id' => 'required|exists:questions,id',
            'responses.*.answer_value' => 'required_without:responses.*.answer_text',
            'responses.*.answer_text' => 'required_without:responses.*.answer_value'
        ];
    }
}
```