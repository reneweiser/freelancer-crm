# Feature: AI Agent Integration (REST API + MCP Client)

## Summary

Enable AI agents (Claude Code, LLMs) to interact with the CRM programmatically via a versioned REST API (`/api/v1/*`) and a local Node.js MCP client. This allows automated creation of projects, offers, invoices, and reminders from freeform notes or strategic conversations.

## Requirements

### From Planning Documents

| Category | Selection | Details |
|----------|-----------|---------|
| Goal | New Functionality | Expose CRM via REST API for AI agent integration |
| Users | Developers, System | AI agents (Claude Code) and automation tools |
| Scope | Large | REST API, MCP Client, Token Management UI |
| Timeline | Flexible | No hard deadline, quality over speed |
| Testing | TDD | Write tests first, then implementation |

### Functional Requirements

1. **REST API Layer** - Versioned API (`/api/v1/*`) for Clients, Projects, Invoices, Reminders
2. **MCP Client** - Local Node.js client providing Claude Code integration via Model Context Protocol
3. **AI-Friendly Design** - Structured inputs, helpful error messages with suggestions, batch operations
4. **Secure Access** - Sanctum personal access tokens with rate limiting (60 req/min)
5. **Token Management** - Filament UI for creating, viewing, and revoking API tokens

### Non-Functional Requirements

- Rate limiting: 60 requests per minute per token
- AI-friendly error messages with field-level suggestions
- Transaction-wrapped batch operations
- English language for all API responses
- Feature tests with >85% coverage

### Use Cases

| ID | Use Case | Description |
|----|----------|-------------|
| UC1 | Project from Notes | Parse freeform meeting notes and create structured project with line items |
| UC2 | Strategic Offer | Discuss existing project with Claude, strategize pricing, create optimized offer |
| UC3 | Quick Invoicing | "Invoice the Website project" via natural language |
| UC4 | Client Lookup | "Find all clients from Berlin" |
| UC5 | Status Updates | "Mark project 42 as completed" |
| UC6 | Daily Summary | "What's on my plate today?" |

## Architecture

```
┌───────────────────────────┐           ┌─────────────────────────────────┐
│    Your Local Machine     │           │      Production Server          │
├───────────────────────────┤           ├─────────────────────────────────┤
│                           │           │                                 │
│  ┌─────────────────────┐  │   HTTPS   │  ┌───────────────────────────┐  │
│  │    Claude Code      │  │           │  │       REST API            │  │
│  └──────────┬──────────┘  │           │  │       /api/v1/*           │  │
│             │ stdio       │           │  └─────────────┬─────────────┘  │
│             ▼             │           │               │                 │
│  ┌─────────────────────┐  │           │               ▼                 │
│  │   MCP Client        │──┼───────────┼──►┌───────────────────────────┐ │
│  │   (Node.js)         │  │           │   │   Laravel Application     │ │
│  └─────────────────────┘  │           │   │   + Sanctum Auth          │ │
│                           │           │   └─────────────┬─────────────┘ │
└───────────────────────────┘           │               │                 │
                                        │               ▼                 │
                                        │   ┌───────────────────────────┐ │
                                        │   │        Database           │ │
                                        │   └───────────────────────────┘ │
                                        └─────────────────────────────────┘
```

**Key Points:**
- MCP Client runs locally where Claude Code runs
- Claude Code spawns MCP Client as subprocess (communicates via stdin/stdout)
- MCP Client makes HTTPS requests to production REST API
- Authentication via Sanctum bearer tokens

## Data Model

### New Tables

No new database tables required. Uses existing Sanctum `personal_access_tokens` table.

### Authentication

```php
// User model - add HasApiTokens trait
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
}
```

## API Design

### Base URL & Authentication

```
Base URL: /api/v1/
Authentication: Bearer {sanctum-token}
Rate Limit: 60 requests/minute
```

### Endpoints Overview

| Resource | Method | Endpoint | Description |
|----------|--------|----------|-------------|
| **Clients** | GET | /clients | List clients (paginated, searchable) |
| | GET | /clients/{id} | Get client details |
| | POST | /clients | Create client |
| | PUT | /clients/{id} | Update client |
| | DELETE | /clients/{id} | Soft delete client |
| **Projects** | GET | /projects | List projects (filterable) |
| | GET | /projects/{id} | Get project with items |
| | POST | /projects | Create project with items |
| | PUT | /projects/{id} | Update project |
| | DELETE | /projects/{id} | Soft delete project |
| | POST | /projects/{id}/items | Add item to project |
| **Invoices** | GET | /invoices | List invoices |
| | GET | /invoices/{id} | Get invoice with items |
| | POST | /invoices | Create invoice |
| | POST | /invoices/from-project/{id} | Create from project |
| | PUT | /invoices/{id} | Update invoice |
| | POST | /invoices/{id}/send | Queue email sending |
| **Reminders** | GET | /reminders | List reminders (filterable) |
| | POST | /reminders | Create reminder |
| | PUT | /reminders/{id} | Update reminder |
| | DELETE | /reminders/{id} | Delete reminder |
| | POST | /reminders/{id}/complete | Mark complete |
| | POST | /reminders/{id}/snooze | Snooze reminder |
| **AI Helpers** | POST | /ai/batch | Batch operations |
| | POST | /ai/validate | Validation dry-run |
| **Stats** | GET | /stats | Dashboard statistics |

### Response Format

#### Success Response
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

#### Error Response (AI-Friendly)
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

### Example Requests

#### Create Project with Items
```json
POST /api/v1/projects

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

#### Batch Operations
```json
POST /api/v1/ai/batch

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

## Component Design

### Server-Side (Laravel)

#### Directory Structure

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

app/Filament/Pages/
└── ApiTokens.php

resources/views/filament/pages/
└── api-tokens.blade.php

routes/
└── api.php
```

#### ApiController (Base)

```php
namespace App\Http\Controllers\Api\V1;

class ApiController extends Controller
{
    protected function success($data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
        ], $status);
    }

    protected function error(string $code, string $message, array $fields = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'fields' => $fields,
            ],
        ], $status);
    }

    protected function paginated($resource, $collection): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $resource::collection($collection),
            'meta' => [
                'current_page' => $collection->currentPage(),
                'per_page' => $collection->perPage(),
                'total' => $collection->total(),
            ],
        ]);
    }
}
```

### Client-Side (Node.js MCP)

#### Directory Structure

```
~/.claude/mcp-servers/reneweiser-crm/
├── package.json
├── index.js          # Main MCP server
├── tools/
│   ├── clients.js    # Client-related tools
│   ├── projects.js   # Project-related tools
│   ├── invoices.js   # Invoice-related tools
│   ├── reminders.js  # Reminder-related tools
│   └── context.js    # Stats, validation tools
└── lib/
    └── api-client.js # HTTP client wrapper
```

#### MCP Tools

| Tool | Description |
|------|-------------|
| `list-clients` | Search and list clients |
| `get-client` | Get detailed client info |
| `create-client` | Create a new client |
| `list-projects` | List projects with filters |
| `get-project` | Get project with items |
| `create-project` | Create project with items |
| `update-project-status` | Change project status |
| `create-invoice-from-project` | Generate invoice from project |
| `send-invoice` | Queue invoice email |
| `list-reminders` | List pending reminders |
| `create-reminder` | Create reminder |
| `complete-reminder` | Mark reminder complete |
| `get-stats` | Get dashboard statistics |
| `validate-data` | Dry-run validation |

### Token Management UI

**File:** `app/Filament/Pages/ApiTokens.php`

Features:
- Create new token with name
- Display plain token once after creation (user must copy)
- List active tokens with last used timestamp
- Revoke tokens with confirmation
- Usage instructions for Claude Code configuration

## Error Handling

| Scenario | Behavior |
|----------|----------|
| Invalid token | 401 Unauthorized with UNAUTHORIZED code |
| Missing token | 401 Unauthorized |
| Rate limit exceeded | 429 Too Many Requests with RATE_LIMITED code |
| Validation error | 422 with field-level errors and suggestions |
| Resource not found | 404 with NOT_FOUND code |
| Server error | 500 with SERVER_ERROR code |
| Batch operation failure | Rollback all operations, return first error |

## Testing Strategy

### Test Categories

| Type | Focus | Tools |
|------|-------|-------|
| Feature | API endpoints, authentication | Pest + RefreshDatabase |
| Unit | Response formatting, validation | Pest |
| Integration | End-to-end workflows | Pest |

### Test Cases

**Authentication (tests/Feature/Api/V1/AuthenticationTest.php)**
- `it('authenticates with valid token')`
- `it('rejects invalid token')`
- `it('rejects missing token')`
- `it('enforces rate limiting')`

**Clients (tests/Feature/Api/V1/ClientApiTest.php)**
- `it('lists clients with pagination')`
- `it('searches clients by name')`
- `it('filters clients by type')`
- `it('creates a company client')`
- `it('creates an individual client')`
- `it('validates required fields')`
- `it('soft deletes clients')`

**Projects (tests/Feature/Api/V1/ProjectApiTest.php)**
- `it('creates project with items')`
- `it('validates client exists')`
- `it('filters by status')`
- `it('includes items in response')`

**Invoices (tests/Feature/Api/V1/InvoiceApiTest.php)**
- `it('creates invoice from project')`
- `it('queues invoice email')`
- `it('prevents sending already sent invoice')`

**Batch Operations (tests/Feature/Api/V1/BatchOperationsTest.php)**
- `it('executes batch operations atomically')`
- `it('resolves $ref references')`
- `it('rolls back on error')`

**Validation (tests/Feature/Api/V1/ValidationEndpointTest.php)**
- `it('validates without persisting')`
- `it('returns field-level errors')`

## Implementation Tasks

### Phase 1: Foundation

- [ ] **Install and configure Sanctum** `priority:1` `phase:foundation`
  - files: config/sanctum.php, app/Models/User.php
  - [ ] Run `sail composer require laravel/sanctum`
  - [ ] Publish config and migrations
  - [ ] Add HasApiTokens trait to User model
  - [ ] Run migrations

- [ ] **Create API route structure** `priority:1` `phase:foundation`
  - files: routes/api.php, bootstrap/app.php
  - [ ] Create v1 prefix group with auth:sanctum middleware
  - [ ] Configure throttle middleware (60 req/min)

- [ ] **Create base API controller** `priority:1` `phase:foundation`
  - files: app/Http/Controllers/Api/V1/ApiController.php
  - [ ] Implement success(), error(), paginated() methods
  - [ ] Add AI-friendly error formatting

- [ ] **Configure exception handling** `priority:1` `phase:foundation`
  - files: bootstrap/app.php
  - [ ] Format validation errors for AI consumption
  - [ ] Include field-level suggestions in errors

### Phase 2: Core CRUD API

- [ ] **Client API** `priority:2` `phase:api` `deps:Phase 1`
  - files: app/Http/Controllers/Api/V1/ClientController.php, app/Http/Resources/Api/V1/ClientResource.php, app/Http/Requests/Api/V1/StoreClientRequest.php, tests/Feature/Api/V1/ClientApiTest.php
  - [ ] Create controller with CRUD methods
  - [ ] Create resource and collection classes
  - [ ] Create form request with validation
  - [ ] Write feature tests

- [ ] **Project API** `priority:2` `phase:api` `deps:Phase 1`
  - files: app/Http/Controllers/Api/V1/ProjectController.php, app/Http/Resources/Api/V1/ProjectResource.php, tests/Feature/Api/V1/ProjectApiTest.php
  - [ ] Create controller with CRUD + addItem
  - [ ] Handle nested item creation
  - [ ] Write feature tests

- [ ] **Invoice API** `priority:2` `phase:api` `deps:Phase 1`
  - files: app/Http/Controllers/Api/V1/InvoiceController.php, app/Http/Resources/Api/V1/InvoiceResource.php, tests/Feature/Api/V1/InvoiceApiTest.php
  - [ ] Create controller with CRUD
  - [ ] Add fromProject action using InvoiceCreationService
  - [ ] Add send action
  - [ ] Write feature tests

- [ ] **Reminder API** `priority:2` `phase:api` `deps:Phase 1`
  - files: app/Http/Controllers/Api/V1/ReminderController.php, app/Http/Resources/Api/V1/ReminderResource.php, tests/Feature/Api/V1/ReminderApiTest.php
  - [ ] Create controller with CRUD + complete/snooze
  - [ ] Handle relative date parsing
  - [ ] Write feature tests

### Phase 3: AI Helper Endpoints

- [ ] **Batch operations endpoint** `priority:3` `phase:ai-helpers` `deps:Phase 2`
  - files: app/Http/Controllers/Api/V1/AiController.php, tests/Feature/Api/V1/BatchOperationsTest.php
  - [ ] Implement transaction-wrapped batch execution
  - [ ] Handle $ref: references between operations
  - [ ] Write feature tests

- [ ] **Validation endpoint** `priority:3` `phase:ai-helpers` `deps:Phase 2`
  - files: app/Http/Controllers/Api/V1/AiController.php (extend), tests/Feature/Api/V1/ValidationEndpointTest.php
  - [ ] Implement dry-run validation
  - [ ] Include field-level suggestions
  - [ ] Write feature tests

- [ ] **Stats endpoint** `priority:3` `phase:ai-helpers` `deps:Phase 2`
  - files: app/Http/Controllers/Api/V1/StatsController.php, tests/Feature/Api/V1/StatsApiTest.php
  - [ ] Aggregate revenue, invoice, project, reminder stats
  - [ ] Write feature tests

### Phase 4: MCP Client (Node.js)

- [ ] **MCP Client setup** `priority:4` `phase:mcp-client` `deps:Phase 2`
  - files: ~/.claude/mcp-servers/reneweiser-crm/package.json, ~/.claude/mcp-servers/reneweiser-crm/index.js, ~/.claude/mcp-servers/reneweiser-crm/lib/api-client.js
  - [ ] Create directory structure
  - [ ] Initialize npm project
  - [ ] Install @modelcontextprotocol/sdk
  - [ ] Create main server and API client

- [ ] **Client tools** `priority:4` `phase:mcp-client`
  - files: ~/.claude/mcp-servers/reneweiser-crm/tools/clients.js
  - [ ] Implement list-clients, get-client, create-client tools

- [ ] **Project tools** `priority:4` `phase:mcp-client`
  - files: ~/.claude/mcp-servers/reneweiser-crm/tools/projects.js
  - [ ] Implement list-projects, get-project, create-project, update-project-status tools

- [ ] **Invoice tools** `priority:4` `phase:mcp-client`
  - files: ~/.claude/mcp-servers/reneweiser-crm/tools/invoices.js
  - [ ] Implement create-invoice-from-project, send-invoice tools

- [ ] **Reminder tools** `priority:4` `phase:mcp-client`
  - files: ~/.claude/mcp-servers/reneweiser-crm/tools/reminders.js
  - [ ] Implement list-reminders, create-reminder, complete-reminder tools

- [ ] **Context tools** `priority:4` `phase:mcp-client`
  - files: ~/.claude/mcp-servers/reneweiser-crm/tools/context.js
  - [ ] Implement get-stats, validate-data tools

### Phase 5: Token Management UI

- [ ] **Filament Token Page** `priority:5` `phase:ui` `deps:Phase 1`
  - files: app/Filament/Pages/ApiTokens.php, resources/views/filament/pages/api-tokens.blade.php
  - [ ] Create page with token creation form
  - [ ] Show plain token once after creation
  - [ ] List active tokens with last used timestamp
  - [ ] Implement token revocation
  - [ ] Add Claude Code configuration instructions

### Phase 6: Documentation & Testing

- [ ] **MCP Documentation** `priority:6` `phase:docs` `deps:Phase 4`
  - files: docs/mcp-tools.md
  - [ ] Document all tools with parameters
  - [ ] Include example prompts for common use cases
  - [ ] Add Claude Code configuration instructions

- [ ] **Integration tests** `priority:6` `phase:test` `deps:Phase 3`
  - files: tests/Feature/Api/V1/IntegrationTest.php
  - [ ] End-to-end test: create project via API
  - [ ] End-to-end test: invoice from project
  - [ ] Rate limiting test

- [ ] **Final verification** `priority:6` `phase:verify`
  - [ ] Run `sail test --filter=Api`
  - [ ] Run `sail pint`
  - [ ] Test MCP client connection with Claude Code

## File Summary

### Server-side (Laravel)

| Category | Count |
|----------|-------|
| Controllers | 6 |
| API Resources | 10 |
| Form Requests | 8 |
| Filament Pages | 1 |
| Views | 1 |
| Tests | 12 |
| **Server Total** | **~38 files** |

### Client-side (Node.js MCP)

| Category | Count |
|----------|-------|
| Main entry | 1 |
| Lib (api-client) | 1 |
| Tool modules | 5 |
| Config | 1 |
| **Client Total** | **~8 files** |

### Documentation

| Category | Count |
|----------|-------|
| MCP tool docs | 1 |
| Design doc | 1 |
| **Docs Total** | **~2 files** |

**Grand Total:** ~48 files

## Out of Scope

- OAuth2 / third-party app authorization
- Webhooks for real-time notifications
- Public API documentation (Swagger/OpenAPI)
- Granular permission scopes per token
- MCP Resources (read-only context) - tools only for MVP

## Success Criteria

1. Claude Code can create a project with line items via MCP tool
2. Claude Code can query existing projects and clients for context
3. API responses include AI-friendly error messages with suggestions
4. Rate limiting prevents abuse (60 req/min per token)
5. All API endpoints have feature tests with >85% coverage

## Claude Code Configuration

After implementation, users add to their Claude Code config:

```json
// ~/.claude/mcp_servers.json
{
  "mcpServers": {
    "reneweiser-crm": {
      "command": "node",
      "args": ["/home/rweiser/.claude/mcp-servers/reneweiser-crm/index.js"],
      "env": {
        "CRM_API_URL": "https://crm.yourdomain.de/api/v1",
        "CRM_API_TOKEN": "your-sanctum-token-here"
      }
    }
  }
}
```
