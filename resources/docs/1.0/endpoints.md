# API Endpoints

- [Surveys](#surveys)
- [Questions](#questions)
- [Responses](#responses)
- [Authentication](#auth)

<a name="surveys"></a>
## Surveys

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/surveys` | List all surveys |
| POST | `/api/surveys` | Create new survey |
| GET | `/api/surveys/{id}` | Get survey details |
| PUT | `/api/surveys/{id}` | Update survey |
| DELETE | `/api/surveys/{id}` | Delete survey |

### Example Request

```http
POST /api/surveys
Authorization: Bearer {token}
Content-Type: application/json

{
    "title": "Customer Satisfaction",
    "description": "Monthly feedback survey",
    "questions": [
        {
            "text": "How satisfied are you?",
            "type": "rating",
            "required": true
        }
    ]
}
```

<a name="questions"></a>
## Questions

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/surveys/{survey}/questions` | List questions |
| POST | `/api/surveys/{survey}/questions` | Add question |
| PUT | `/api/questions/{id}` | Update question |
| DELETE | `/api/questions/{id}` | Delete question |

<a name="responses"></a>
## Responses

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/surveys/{survey}/responses` | Submit responses |
| GET | `/api/surveys/{survey}/responses` | Get responses |

<a name="auth"></a>
## Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/login` | Get API token |
| POST | `/api/logout` | Revoke token |
| GET | `/api/user` | Get user info |