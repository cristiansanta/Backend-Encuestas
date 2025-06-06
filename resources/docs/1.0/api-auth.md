# API Authentication

- [Overview](#overview)
- [Token Generation](#token-generation)
- [Authentication Flow](#auth-flow)
- [Security Best Practices](#security)

<a name="overview"></a>
## Overview

The API uses Laravel Sanctum for token-based authentication. Each request must include a valid API token in the Authorization header.

<a name="token-generation"></a>
## Token Generation

```php
// Example token generation
$token = $user->createToken('api-token')->plainTextToken;
```

### API Login Endpoint

```http
POST /api/login
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "password"
}
```

### Response Format

```json
{
    "token": "1|2YZqGQVuylYcdLkheVXLqzGGAftdJVzytEXm4QAs",
    "token_type": "Bearer"
}
```

<a name="auth-flow"></a>
## Authentication Flow

1. Client sends credentials to `/api/login`
2. Server validates and returns token
3. Client includes token in subsequent requests:

```http
GET /api/surveys
Authorization: Bearer 1|2YZqGQVuylYcdLkheVXLqzGGAftdJVzytEXm4QAs
```

<a name="security"></a>
## Security Best Practices

- Tokens expire after 24 hours
- Use HTTPS in production
- Implement rate limiting
- Rotate tokens periodically