# Iteration 3: AI Agent Integration

## Overview

Enable AI agents (Claude Code, LLMs) to interact with the CRM programmatically, allowing automated creation of projects, offers, invoices, and reminders from freeform notes or strategic conversations.

## Goals

1. **REST API Layer** - Expose CRM functionality via versioned API (`/api/v1/*`) on production server
2. **MCP Client** - Local Node.js client providing Claude Code integration via Model Context Protocol
3. **AI-Friendly Design** - Structured inputs, helpful error messages, batch operations
4. **Secure Access** - Sanctum personal access tokens with rate limiting

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

## Primary Use Cases

### UC1: Create Project from Freeform Notes
User provides a text file with meeting notes → AI parses and creates a structured project with line items.

### UC2: Strategic Offer Creation via AI Collaboration
User discusses an existing project with Claude → AI helps strategize pricing/phasing → AI creates optimized offer.

### Additional Use Cases
- Quick invoicing: "Invoice the Website project"
- Client lookup: "Find all clients from Berlin"
- Status updates: "Mark project 42 as completed"
- Daily summary: "What's on my plate today?"

## Scope

### In Scope
- REST API for Clients, Projects, Invoices, Reminders (CRUD)
- Batch operations endpoint
- Validation dry-run endpoint
- Dashboard stats endpoint
- MCP Client (Node.js) with 10-12 tools
- Token management UI in Filament
- API feature tests
- MCP tool documentation

### Out of Scope
- OAuth2 / third-party app authorization
- Webhooks for real-time notifications
- Public API documentation (Swagger/OpenAPI)
- Granular permission scopes per token

## Success Criteria

1. Claude Code can create a project with line items via MCP tool
2. Claude Code can query existing projects and clients for context
3. API responses include AI-friendly error messages with suggestions
4. Rate limiting prevents abuse (60 req/min per token)
5. All API endpoints have feature tests with >85% coverage

## Dependencies

**Server-side (Laravel):**
- Laravel Sanctum (API tokens)
- Existing service layer (InvoiceCreationService, etc.)

**Client-side (Local):**
- Node.js 18+
- @modelcontextprotocol/sdk (MCP SDK for Node.js)

## Implementation Phases

| Phase | Description | Priority |
|-------|-------------|----------|
| 1. Foundation | Sanctum setup, API structure, rate limiting | High |
| 2. Core CRUD API | Client, Project, Invoice, Reminder endpoints | High |
| 3. AI Helpers | Batch, validate, stats endpoints | Medium |
| 4. MCP Client | Node.js client with tool definitions | High |
| 5. Token Management | Filament UI for tokens | Medium |
| 6. Documentation | MCP docs, tests, examples | Medium |

## Technical Decisions

- **API Language:** English (all responses, error messages)
- **Versioning:** URL-based (`/api/v1/*`)
- **Authentication:** Sanctum personal access tokens
- **Rate Limiting:** 60 requests/minute per token
- **Batch Operations:** Transaction-wrapped with reference support (`$ref:0`)
- **MCP Client:** Node.js with @modelcontextprotocol/sdk (runs locally, calls remote API)
