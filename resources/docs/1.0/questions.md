# Gestión de Preguntas

- [Modelo Question](#question-model)
- [Tipos de Preguntas](#question-types)
- [Validación](#validation)
- [Ejemplos de Uso](#usage)

<a name="question-model"></a>
## Modelo Question

```php
namespace App\Models;

class Question extends Model
{
    protected $fillable = [
        'survey_id',
        'question_text',
        'type',
        'options',
        'required',
        'order'
    ];

    protected $casts = [
        'options' => 'array',
        'required' => 'boolean'
    ];
}
```

<a name="question-types"></a>
## Tipos de Preguntas

```php
namespace App\Enums;

enum QuestionType: string
{
    case MULTIPLE_CHOICE = 'multiple_choice';
    case TEXT = 'text';
    case RATING = 'rating';
    case DATE = 'date';
}
```

<a name="validation"></a>
## Validación

```php
namespace App\Http\Requests;

class StoreQuestionRequest extends FormRequest
{
    public function rules()
    {
        return [
            'question_text' => 'required|string|max:500',
            'type' => ['required', Rule::enum(QuestionType::class)],
            'options' => [
                'required_if:type,multiple_choice',
                'array',
                'min:2'
            ],
            'required' => 'boolean'
        ];
    }
}
```

<a name="usage"></a>
## Ejemplos de Uso

### Crear Pregunta
```php
$question = Question::create([
    'survey_id' => $surveyId,
    'question_text' => '¿Qué tan satisfecho está con nuestro servicio?',
    'type' => QuestionType::RATING,
    'required' => true,
    'order' => 1
]);
```

### Actualizar Orden de Preguntas
```php
public function reorderQuestions(array $questionIds)
{
    collect($questionIds)->each(function ($id, $index) {
        Question::where('id', $id)->update(['order' => $index + 1]);
    });
}
```