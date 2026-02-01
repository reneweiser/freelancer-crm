# REST API Design

## Overview

Versioned REST API exposing CRM functionality to AI agents and automation tools.

## Authentication

### Sanctum Personal Access Tokens

```php
// User model
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
}
```

### Token Creation (via Filament UI)
```php
$token = $user->createToken('claude-code-integration');
$plainTextToken = $token->plainTextToken; // Show once, store securely
```

### Request Authentication
```http
GET /api/v1/clients
Authorization: Bearer {token}
Accept: application/json
```

## API Structure

### Base URL
```
/api/v1/
```

### Route Registration
```php
// routes/api.php
Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Clients
    Route::apiResource('clients', ClientController::class);

    // Projects
    Route::apiResource('projects', ProjectController::class);
    Route::post('projects/{project}/items', [ProjectController::class, 'addItem']);

    // Invoices
    Route::apiResource('invoices', InvoiceController::class);
    Route::post('invoices/from-project/{project}', [InvoiceController::class, 'fromProject']);
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send']);

    // Reminders
    Route::apiResource('reminders', ReminderController::class);
    Route::post('reminders/{reminder}/complete', [ReminderController::class, 'complete']);
    Route::post('reminders/{reminder}/snooze', [ReminderController::class, 'snooze']);

    // AI Helpers
    Route::prefix('ai')->group(function () {
        Route::post('batch', [AiController::class, 'batch']);
        Route::post('validate', [AiController::class, 'validate']);
    });

    // Stats
    Route::get('stats', [StatsController::class, 'index']);
});
```

## Endpoints

### Clients

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /clients | List clients (paginated, searchable) |
| GET | /clients/{id} | Get client details |
| POST | /clients | Create client |
| PUT | /clients/{id} | Update client |
| DELETE | /clients/{id} | Soft delete client |

#### Create Client Request
```json
{
  "type": "company",
  "company_name": "Acme GmbH",
  "contact_name": "Max Mustermann",
  "email": "max@acme.de",
  "phone": "+49 123 456789",
  "street": "Hauptstraße 1",
  "zip": "12345",
  "city": "Berlin",
  "country": "DE",
  "vat_id": "DE123456789",
  "notes": "Referred by existing client"
}
```

#### List Clients Query Parameters
```
GET /clients?search=acme&type=company&per_page=25
```

### Projects

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /projects | List projects (filterable) |
| GET | /projects/{id} | Get project with items |
| POST | /projects | Create project with items |
| PUT | /projects/{id} | Update project |
| DELETE | /projects/{id} | Soft delete project |
| POST | /projects/{id}/items | Add item to project |

#### Create Project Request
```json
{
  "client_id": 1,
  "title": "Website Redesign",
  "description": "Complete redesign of corporate website",
  "type": "fixed",
  "status": "draft",
  "valid_until": "2026-03-15",
  "items": [
    {
      "description": "Design & Konzeption",
      "quantity": 1,
      "unit": "pauschal",
      "unit_price": 2500.00
    },
    {
      "description": "Frontend-Entwicklung",
      "quantity": 40,
      "unit": "Stunden",
      "unit_price": 95.00
    }
  ]
}
```

#### List Projects Query Parameters
```
GET /projects?client_id=5&status=draft,sent&per_page=25
```

### Invoices

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /invoices | List invoices |
| GET | /invoices/{id} | Get invoice with items |
| POST | /invoices | Create invoice |
| POST | /invoices/from-project/{id} | Create from project |
| PUT | /invoices/{id} | Update invoice |
| POST | /invoices/{id}/send | Queue email sending |

#### Create Invoice from Project
```json
POST /invoices/from-project/42

// Response
{
  "success": true,
  "data": {
    "id": 15,
    "number": "2026-015",
    "client": { "id": 5, "name": "Acme GmbH" },
    "total": 6300.00,
    "status": "draft",
    "items_count": 3,
    "url": "http://crm.local/invoices/15/edit"
  }
}
```

### Reminders

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /reminders | List reminders (filterable) |
| POST | /reminders | Create reminder |
| PUT | /reminders/{id} | Update reminder |
| DELETE | /reminders/{id} | Delete reminder |
| POST | /reminders/{id}/complete | Mark complete |
| POST | /reminders/{id}/snooze | Snooze reminder |

#### Create Reminder Request
```json
{
  "remindable_type": "project",
  "remindable_id": 42,
  "title": "Follow up on Phase 2 proposal",
  "description": "Client should have reviewed MVP by now",
  "due_at": "2026-03-01",
  "priority": "normal"
}
```

### AI Helpers

#### Batch Operations
```json
POST /ai/batch

{
  "operations": [
    {
      "method": "POST",
      "resource": "clients",
      "ref": "new_client",
      "data": {
        "type": "company",
        "company_name": "New Client GmbH"
      }
    },
    {
      "method": "POST",
      "resource": "projects",
      "data": {
        "client_id": "$ref:new_client.id",
        "title": "New Project",
        "items": [...]
      }
    }
  ]
}
```

#### Validation Dry-Run
```json
POST /ai/validate

{
  "resource": "projects",
  "data": {
    "client_id": 999,
    "title": "",
    "items": []
  }
}

// Response
{
  "valid": false,
  "errors": {
    "client_id": ["Client with ID 999 does not exist."],
    "title": ["The title field is required."],
    "items": ["At least one item is required."]
  }
}
```

### Stats
```json
GET /stats

{
  "revenue": {
    "this_month": 12500.00,
    "this_year": 87500.00
  },
  "invoices": {
    "unpaid_count": 3,
    "unpaid_total": 8750.00,
    "overdue_count": 1
  },
  "projects": {
    "active_count": 5,
    "draft_offers_count": 2
  },
  "reminders": {
    "due_today": 2,
    "upcoming_week": 5
  }
}
```

## Response Format

### Success Response
```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 42
  }
}
```

### Error Response (AI-Friendly)
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The input data is invalid.",
    "fields": {
      "client_id": {
        "value": 999,
        "errors": ["Client with ID 999 does not exist."],
        "suggestion": "Retrieve available clients with GET /api/v1/clients"
      },
      "items.0.unit_price": {
        "value": -50,
        "errors": ["The price must be greater than 0."],
        "suggestion": "Use a positive value, e.g., 95.00"
      }
    },
    "hint": "Use POST /api/v1/ai/validate for pre-validation"
  }
}
```

### Error Codes
| Code | HTTP Status | Description |
|------|-------------|-------------|
| VALIDATION_ERROR | 422 | Input validation failed |
| NOT_FOUND | 404 | Resource not found |
| UNAUTHORIZED | 401 | Missing or invalid token |
| FORBIDDEN | 403 | No permission for action |
| RATE_LIMITED | 429 | Too many requests |
| SERVER_ERROR | 500 | Internal server error |

## Rate Limiting

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->throttleApi('60,1'); // 60 requests per minute
})
```

Response headers:
```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1706812800
```

## Files to Create

```
app/Http/Controllers/Api/V1/
├── ApiController.php          # Base controller with response helpers
├── ClientController.php
├── ProjectController.php
├── InvoiceController.php
├── ReminderController.php
├── AiController.php           # Batch, validate endpoints
└── StatsController.php

app/Http/Resources/Api/V1/
├── ClientResource.php
├── ClientCollection.php
├── ProjectResource.php
├── ProjectCollection.php
├── InvoiceResource.php
├── ReminderResource.php
└── StatsResource.php

app/Http/Requests/Api/V1/
├── StoreClientRequest.php
├── UpdateClientRequest.php
├── StoreProjectRequest.php
├── StoreInvoiceRequest.php
├── StoreReminderRequest.php
├── BatchRequest.php
└── ValidateRequest.php

routes/
└── api.php
```
