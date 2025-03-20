# Testing

- [Configuración](#setup)
- [Tests Unitarios](#unit-tests)
- [Tests de Integración](#integration-tests)
- [Tests de API](#api-tests)

<a name="setup"></a>
## Configuración

Configurar el entorno de testing en `.env.testing`:

```env
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=encuestas_test
DB_USERNAME=postgres
DB_PASSWORD=sena123
```

Ejecutar las migraciones para testing:

```bash
php artisan migrate --env=testing
```

<a name="unit-tests"></a>
## Tests Unitarios

### Ejemplo de Test de Modelo

```php
namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Survey;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SurveyTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_survey()
    {
        $survey = Survey::factory()->create();
        $this->assertInstanceOf(Survey::class, $survey);
    }

    public function test_survey_has_questions()
    {
        $survey = Survey::factory()
            ->hasQuestions(3)
            ->create();
            
        $this->assertEquals(3, $survey->questions->count());
    }
}
```

<a name="integration-tests"></a>
## Tests de Integración

### Test de Controlador

```php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Survey;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SurveyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_survey()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->postJson('/api/surveys', [
                'title' => 'Test Survey',
                'description' => 'Test Description'
            ]);

        $response->assertStatus(201);
    }
}
```

<a name="api-tests"></a>
## Tests de API

```php
namespace Tests\Feature;

class ApiTest extends TestCase
{
    public function test_api_authentication()
    {
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token']);
    }
}
```

### Ejecutar Tests

```bash
# Ejecutar todos los tests
php artisan test

# Ejecutar tests específicos
php artisan test --filter=SurveyTest

# Ejecutar con cobertura
php artisan test --coverage
```