# Implementation Tasks

## Overview

Detailed task breakdown for implementing AI Agent Integration in iteration-3.

## Phase 1: Foundation

### 1.1 Install and Configure Sanctum
- [ ] Run `sail composer require laravel/sanctum`
- [ ] Publish Sanctum config: `sail artisan vendor:publish --tag=sanctum-config`
- [ ] Publish Sanctum migrations: `sail artisan vendor:publish --tag=sanctum-migrations`
- [ ] Run migrations: `sail artisan migrate`
- [ ] Add `HasApiTokens` trait to User model

**Files:**
- `config/sanctum.php`
- `app/Models/User.php`
- `database/migrations/*_create_personal_access_tokens_table.php`

### 1.2 Create API Route Structure
- [ ] Create `routes/api.php` with v1 prefix
- [ ] Register API routes in `bootstrap/app.php`
- [ ] Configure Sanctum guard for API
- [ ] Add throttle middleware (60 req/min)

**Files:**
- `routes/api.php`
- `bootstrap/app.php`

### 1.3 Create Base API Controller
- [ ] Create `ApiController` with response helper methods
- [ ] Implement `success()`, `error()`, `paginated()` methods
- [ ] Add AI-friendly error formatting trait

**Files:**
- `app/Http/Controllers/Api/V1/ApiController.php`
- `app/Http/Controllers/Api/Traits/FormatsApiResponses.php`

### 1.4 Configure Exception Handling
- [ ] Add API exception handling in `bootstrap/app.php`
- [ ] Format validation errors for AI consumption
- [ ] Include field-level suggestions in errors

**Files:**
- `bootstrap/app.php`

---

## Phase 2: Core CRUD API

### 2.1 Client API
- [ ] Create `ClientController` with index, show, store, update, destroy
- [ ] Create `ClientResource` for JSON transformation
- [ ] Create `ClientCollection` for paginated lists
- [ ] Create `StoreClientRequest` with validation rules
- [ ] Create `UpdateClientRequest`
- [ ] Add search scope to Client model (if not exists)
- [ ] Write feature tests

**Files:**
- `app/Http/Controllers/Api/V1/ClientController.php`
- `app/Http/Resources/Api/V1/ClientResource.php`
- `app/Http/Resources/Api/V1/ClientCollection.php`
- `app/Http/Requests/Api/V1/StoreClientRequest.php`
- `app/Http/Requests/Api/V1/UpdateClientRequest.php`
- `tests/Feature/Api/V1/ClientApiTest.php`

### 2.2 Project API
- [ ] Create `ProjectController` with CRUD + addItem action
- [ ] Create `ProjectResource` with nested items
- [ ] Create `ProjectItemResource`
- [ ] Create `StoreProjectRequest` with items validation
- [ ] Handle nested item creation in controller
- [ ] Write feature tests

**Files:**
- `app/Http/Controllers/Api/V1/ProjectController.php`
- `app/Http/Resources/Api/V1/ProjectResource.php`
- `app/Http/Resources/Api/V1/ProjectItemResource.php`
- `app/Http/Requests/Api/V1/StoreProjectRequest.php`
- `tests/Feature/Api/V1/ProjectApiTest.php`

### 2.3 Invoice API
- [ ] Create `InvoiceController` with CRUD
- [ ] Add `fromProject` action using `InvoiceCreationService`
- [ ] Add `send` action using `SendInvoiceEmail` job
- [ ] Create `InvoiceResource` with nested items
- [ ] Write feature tests

**Files:**
- `app/Http/Controllers/Api/V1/InvoiceController.php`
- `app/Http/Resources/Api/V1/InvoiceResource.php`
- `app/Http/Requests/Api/V1/StoreInvoiceRequest.php`
- `tests/Feature/Api/V1/InvoiceApiTest.php`

### 2.4 Reminder API
- [ ] Create `ReminderController` with CRUD + complete/snooze
- [ ] Create `ReminderResource` with polymorphic remindable
- [ ] Handle relative date parsing (`+7 days`)
- [ ] Write feature tests

**Files:**
- `app/Http/Controllers/Api/V1/ReminderController.php`
- `app/Http/Resources/Api/V1/ReminderResource.php`
- `app/Http/Requests/Api/V1/StoreReminderRequest.php`
- `tests/Feature/Api/V1/ReminderApiTest.php`

---

## Phase 3: AI Helper Endpoints

### 3.1 Batch Operations
- [ ] Create `AiController` with batch action
- [ ] Implement transaction wrapper for atomic operations
- [ ] Handle `$ref:` references between operations
- [ ] Return aggregated results
- [ ] Write feature tests

**Files:**
- `app/Http/Controllers/Api/V1/AiController.php`
- `app/Http/Requests/Api/V1/BatchRequest.php`
- `tests/Feature/Api/V1/BatchOperationsTest.php`

### 3.2 Validation Endpoint
- [ ] Add validate action to `AiController`
- [ ] Return validation errors without persisting
- [ ] Include field-level suggestions
- [ ] Write feature tests

**Files:**
- `app/Http/Controllers/Api/V1/AiController.php` (extend)
- `app/Http/Requests/Api/V1/ValidateRequest.php`
- `tests/Feature/Api/V1/ValidationEndpointTest.php`

### 3.3 Stats Endpoint
- [ ] Create `StatsController`
- [ ] Aggregate revenue, invoice, project, reminder stats
- [ ] Create `StatsResource`
- [ ] Write feature tests

**Files:**
- `app/Http/Controllers/Api/V1/StatsController.php`
- `app/Http/Resources/Api/V1/StatsResource.php`
- `tests/Feature/Api/V1/StatsApiTest.php`

---

## Phase 4: MCP Client (Node.js)

> **Location:** `~/.claude/mcp-servers/reneweiser-crm/`
> This runs locally on your machine, not on the server.

### 4.1 Project Setup
- [ ] Create directory `~/.claude/mcp-servers/reneweiser-crm/`
- [ ] Initialize Node.js project (`npm init -y`)
- [ ] Install `@modelcontextprotocol/sdk`
- [ ] Create base `index.js` with MCP server setup
- [ ] Create `lib/api-client.js` HTTP wrapper

**Files:**
- `~/.claude/mcp-servers/reneweiser-crm/package.json`
- `~/.claude/mcp-servers/reneweiser-crm/index.js`
- `~/.claude/mcp-servers/reneweiser-crm/lib/api-client.js`

### 4.2 Client Tools
- [ ] Create `tools/clients.js` with list, get, create tools
- [ ] Define tool schemas (inputSchema)
- [ ] Implement HTTP handlers

**Files:**
- `~/.claude/mcp-servers/reneweiser-crm/tools/clients.js`

### 4.3 Project Tools
- [ ] Create `tools/projects.js` with list, get, create, update-status tools
- [ ] Define tool schemas
- [ ] Implement HTTP handlers

**Files:**
- `~/.claude/mcp-servers/reneweiser-crm/tools/projects.js`

### 4.4 Invoice Tools
- [ ] Create `tools/invoices.js` with from-project, send tools
- [ ] Define tool schemas
- [ ] Implement HTTP handlers

**Files:**
- `~/.claude/mcp-servers/reneweiser-crm/tools/invoices.js`

### 4.5 Reminder Tools
- [ ] Create `tools/reminders.js` with list, create, complete tools
- [ ] Define tool schemas
- [ ] Implement HTTP handlers

**Files:**
- `~/.claude/mcp-servers/reneweiser-crm/tools/reminders.js`

### 4.6 Context Tools
- [ ] Create `tools/context.js` with stats, validate tools
- [ ] Define tool schemas
- [ ] Implement HTTP handlers

**Files:**
- `~/.claude/mcp-servers/reneweiser-crm/tools/context.js`

### 4.7 Claude Code Configuration
- [ ] Configure `~/.claude/mcp_servers.json`
- [ ] Document environment variables (CRM_API_URL, CRM_API_TOKEN)
- [ ] Test connection with Claude Code

---

## Phase 5: Token Management UI

### 5.1 Filament Token Page
- [ ] Create `ApiTokens` Filament page
- [ ] Implement token creation with name
- [ ] Show plain token once after creation
- [ ] List active tokens with last used timestamp
- [ ] Implement token revocation
- [ ] Add usage instructions section

**Files:**
- `app/Filament/Pages/ApiTokens.php`
- `resources/views/filament/pages/api-tokens.blade.php`

---

## Phase 6: Documentation & Testing

### 6.1 MCP Documentation
- [ ] Document all tools with parameters
- [ ] Include example prompts for common use cases
- [ ] Add Claude Code configuration instructions

**Files:**
- `docs/mcp-tools.md`

### 6.2 Integration Tests
- [ ] End-to-end test: create project via API
- [ ] End-to-end test: invoice from project
- [ ] Rate limiting test
- [ ] Token authentication tests

**Files:**
- `tests/Feature/Api/V1/IntegrationTest.php`

### 6.3 Example Prompts
- [ ] Document prompt examples for use cases
- [ ] Include in MCP documentation

---

## Dependencies Between Tasks

```
Phase 1 ─┬─> Phase 2 ─┬─> Phase 3
         │            │
         └─> Phase 4 ─┘
                │
                └─> Phase 5

Phase 6 depends on all previous phases
```

## Estimated File Count

### Server-side (Laravel)

| Category | Files |
|----------|-------|
| Controllers | 6 |
| API Resources | 10 |
| Form Requests | 8 |
| Filament Pages | 1 |
| Views | 1 |
| Tests | 12 |
| **Server Total** | **~38 files** |

### Client-side (Node.js MCP)

| Category | Files |
|----------|-------|
| Main entry | 1 |
| Lib (api-client) | 1 |
| Tool modules | 5 |
| Config | 1 |
| **Client Total** | **~8 files** |

### Documentation

| Category | Files |
|----------|-------|
| MCP tool docs | 1 |
| Planning docs | 5 |
| **Docs Total** | **~6 files** |

**Grand Total:** ~52 files
