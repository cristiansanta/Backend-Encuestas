# Ejemplos de Uso de la API

- [Autenticación](#authentication)
- [Gestión de Encuestas](#surveys)
- [Gestión de Respuestas](#responses)
- [Reportes](#reports)

<a name="authentication"></a>
## Autenticación

### Login y Obtención de Token

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "password"}'
```

### Uso del Token

```bash
export TOKEN="1|2YZqGQVuylYcdLkheVXLqzGGAftdJVzytEXm4QAs"
```

<a name="surveys"></a>
## Gestión de Encuestas

### Crear una Nueva Encuesta

```bash
curl -X POST http://localhost:8000/api/surveys \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Satisfacción del Cliente 2025",
    "description": "Encuesta anual de satisfacción",
    "questions": [
      {
        "text": "¿Cómo calificaría nuestro servicio?",
        "type": "rating",
        "required": true
      },
      {
        "text": "¿Qué podríamos mejorar?",
        "type": "text",
        "required": false
      }
    ]
  }'
```

### Obtener Lista de Encuestas

```bash
curl -X GET http://localhost:8000/api/surveys \
  -H "Authorization: Bearer $TOKEN"
```

<a name="responses"></a>
## Gestión de Respuestas

### Enviar Respuestas

```bash
curl -X POST http://localhost:8000/api/surveys/1/responses \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "responses": [
      {
        "question_id": 1,
        "answer_value": 5
      },
      {
        "question_id": 2,
        "answer_text": "El tiempo de respuesta podría mejorar"
      }
    ]
  }'
```

<a name="reports"></a>
## Reportes

### Obtener Estadísticas de una Encuesta

```bash
curl -X GET http://localhost:8000/api/surveys/1/statistics \
  -H "Authorization: Bearer $TOKEN"
```

### Exportar Resultados

```bash
curl -X GET http://localhost:8000/api/surveys/1/export \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" \
  --output "survey_results.xlsx"
```

### Ejemplo con JavaScript/Fetch

```javascript
const apiClient = {
  token: null,

  async login(email, password) {
    const response = await fetch('http://localhost:8000/api/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ email, password })
    });
    const data = await response.json();
    this.token = data.token;
    return data;
  },

  async createSurvey(surveyData) {
    const response = await fetch('http://localhost:8000/api/surveys', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${this.token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(surveyData)
    });
    return await response.json();
  }
};
```