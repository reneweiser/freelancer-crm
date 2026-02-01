# MCP Tool Specifications

## Overview

This document specifies the MCP tools that Claude Code will have access to. These tools are implemented in the **Node.js MCP Client** (see `05-mcp-client.md`) which runs locally and calls the REST API.

> **Note:** The PHP code examples below illustrate the *logic* each tool should perform.
> The actual implementation is in Node.js - see `05-mcp-client.md` for the real code.

## Architecture Recap

```
Claude Code ──► MCP Client (Node.js, local) ──► REST API (remote) ──► Laravel
```

The MCP Client translates tool calls into HTTP requests to the REST API endpoints defined in `01-rest-api.md`.

## Tool Definitions

### Client Tools

#### list-clients
```php
#[McpTool(
    name: 'list-clients',
    description: 'Search and list clients in the CRM. Use this to find existing clients before creating projects or invoices.'
)]
class ListClientsTool
{
    #[McpParameter('search', 'string', 'Search by company name, contact name, or email')]
    #[McpParameter('type', 'string', 'Filter by type: "company" or "individual"')]
    #[McpParameter('limit', 'integer', 'Maximum results to return (default: 25)')]
    public function handle(array $params): McpResponse
    {
        $clients = Client::query()
            ->when($params['search'] ?? null, fn($q, $s) => $q->search($s))
            ->when($params['type'] ?? null, fn($q, $t) => $q->where('type', $t))
            ->limit($params['limit'] ?? 25)
            ->get(['id', 'type', 'company_name', 'contact_name', 'email', 'city']);

        return McpResponse::success($clients);
    }
}
```

#### get-client
```php
#[McpTool(
    name: 'get-client',
    description: 'Get detailed information about a specific client including their projects and invoices.'
)]
class GetClientTool
{
    #[McpParameter('id', 'integer', 'The client ID', required: true)]
    public function handle(array $params): McpResponse
    {
        $client = Client::with(['projects:id,client_id,title,status', 'invoices:id,client_id,number,status,total'])
            ->findOrFail($params['id']);

        return McpResponse::success($client);
    }
}
```

#### create-client
```php
#[McpTool(
    name: 'create-client',
    description: 'Create a new client in the CRM.'
)]
class CreateClientTool
{
    #[McpParameter('type', 'string', 'Client type: "company" or "individual"', required: true)]
    #[McpParameter('company_name', 'string', 'Company name (required for company type)')]
    #[McpParameter('contact_name', 'string', 'Contact person name')]
    #[McpParameter('email', 'string', 'Email address')]
    #[McpParameter('phone', 'string', 'Phone number')]
    #[McpParameter('street', 'string', 'Street address')]
    #[McpParameter('zip', 'string', 'Postal code')]
    #[McpParameter('city', 'string', 'City')]
    #[McpParameter('country', 'string', 'Country code (default: DE)')]
    #[McpParameter('vat_id', 'string', 'VAT ID for companies')]
    public function handle(array $params): McpResponse
    {
        $validated = Validator::make($params, [
            'type' => 'required|in:company,individual',
            'company_name' => 'required_if:type,company|string',
            'contact_name' => 'nullable|string',
            'email' => 'nullable|email',
            // ... rest of validation
        ])->validate();

        $client = Client::create([
            'user_id' => auth()->id(),
            ...$validated,
        ]);

        return McpResponse::success([
            'id' => $client->id,
            'name' => $client->display_name,
            'message' => "Client '{$client->display_name}' created successfully.",
        ]);
    }
}
```

### Project Tools

#### list-projects
```php
#[McpTool(
    name: 'list-projects',
    description: 'List projects/offers with optional filters. Use to find existing projects for context or invoicing.'
)]
class ListProjectsTool
{
    #[McpParameter('client_id', 'integer', 'Filter by client ID')]
    #[McpParameter('status', 'string', 'Filter by status: draft, sent, accepted, in_progress, completed')]
    #[McpParameter('search', 'string', 'Search in title and description')]
    #[McpParameter('limit', 'integer', 'Maximum results (default: 25)')]
    public function handle(array $params): McpResponse
    {
        $projects = Project::with('client:id,company_name,contact_name')
            ->when($params['client_id'] ?? null, fn($q, $id) => $q->where('client_id', $id))
            ->when($params['status'] ?? null, fn($q, $s) => $q->where('status', $s))
            ->when($params['search'] ?? null, fn($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->limit($params['limit'] ?? 25)
            ->get();

        return McpResponse::success($projects);
    }
}
```

#### get-project
```php
#[McpTool(
    name: 'get-project',
    description: 'Get detailed project information including all line items. Use for context when strategizing offers.'
)]
class GetProjectTool
{
    #[McpParameter('id', 'integer', 'The project ID', required: true)]
    public function handle(array $params): McpResponse
    {
        $project = Project::with(['client', 'items'])
            ->findOrFail($params['id']);

        return McpResponse::success([
            'id' => $project->id,
            'title' => $project->title,
            'description' => $project->description,
            'type' => $project->type->value,
            'status' => $project->status->value,
            'client' => [
                'id' => $project->client->id,
                'name' => $project->client->display_name,
            ],
            'items' => $project->items->map(fn($item) => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'total' => $item->total,
            ]),
            'subtotal' => $project->subtotal,
            'vat_amount' => $project->vat_amount,
            'total' => $project->total,
            'valid_until' => $project->valid_until?->format('Y-m-d'),
        ]);
    }
}
```

#### create-project
```php
#[McpTool(
    name: 'create-project',
    description: 'Create a new project/offer with line items. Use after strategizing with the user about scope and pricing.'
)]
class CreateProjectTool
{
    #[McpParameter('client_id', 'integer', 'ID of the existing client', required: true)]
    #[McpParameter('title', 'string', 'Project title', required: true)]
    #[McpParameter('description', 'string', 'Detailed project description')]
    #[McpParameter('type', 'string', 'Project type: "hourly" or "fixed" (default: fixed)')]
    #[McpParameter('valid_until', 'string', 'Offer validity date (YYYY-MM-DD)')]
    #[McpParameter('items', 'array', 'Line items: [{description, quantity, unit, unit_price}]', required: true)]
    public function handle(array $params): McpResponse
    {
        DB::beginTransaction();

        try {
            $project = Project::create([
                'user_id' => auth()->id(),
                'client_id' => $params['client_id'],
                'title' => $params['title'],
                'description' => $params['description'] ?? null,
                'type' => $params['type'] ?? 'fixed',
                'status' => 'draft',
                'valid_until' => $params['valid_until'] ?? null,
            ]);

            foreach ($params['items'] as $index => $item) {
                $project->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'] ?? 1,
                    'unit' => $item['unit'] ?? 'Stunden',
                    'unit_price' => $item['unit_price'],
                    'position' => $index + 1,
                ]);
            }

            DB::commit();

            return McpResponse::success([
                'id' => $project->id,
                'title' => $project->title,
                'items_count' => count($params['items']),
                'total' => $project->fresh()->total,
                'url' => route('filament.admin.resources.projects.edit', $project),
                'message' => "Project '{$project->title}' created with " . count($params['items']) . " items.",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
```

#### update-project-status
```php
#[McpTool(
    name: 'update-project-status',
    description: 'Change the status of a project (draft → sent → accepted → in_progress → completed).'
)]
class UpdateProjectStatusTool
{
    #[McpParameter('id', 'integer', 'The project ID', required: true)]
    #[McpParameter('status', 'string', 'New status: draft, sent, accepted, declined, in_progress, completed, cancelled', required: true)]
    public function handle(array $params): McpResponse
    {
        $project = Project::findOrFail($params['id']);
        $oldStatus = $project->status;

        // Validate transition
        if (!$project->status->canTransitionTo($params['status'])) {
            return McpResponse::error(
                "Cannot transition from '{$oldStatus->value}' to '{$params['status']}'."
            );
        }

        $project->update(['status' => $params['status']]);

        return McpResponse::success([
            'id' => $project->id,
            'title' => $project->title,
            'old_status' => $oldStatus->value,
            'new_status' => $params['status'],
            'message' => "Project status changed from '{$oldStatus->value}' to '{$params['status']}'.",
        ]);
    }
}
```

### Invoice Tools

#### create-invoice-from-project
```php
#[McpTool(
    name: 'create-invoice-from-project',
    description: 'Generate an invoice from an existing project. Copies all project items to the invoice.'
)]
class CreateInvoiceFromProjectTool
{
    public function __construct(
        private InvoiceCreationService $service
    ) {}

    #[McpParameter('project_id', 'integer', 'The project ID to invoice', required: true)]
    public function handle(array $params): McpResponse
    {
        $project = Project::findOrFail($params['project_id']);

        $invoice = $this->service->createFromProject($project);

        return McpResponse::success([
            'id' => $invoice->id,
            'number' => $invoice->number,
            'total' => $invoice->total,
            'status' => $invoice->status->value,
            'url' => route('filament.admin.resources.invoices.edit', $invoice),
            'message' => "Invoice {$invoice->number} created from project '{$project->title}'.",
        ]);
    }
}
```

#### send-invoice
```php
#[McpTool(
    name: 'send-invoice',
    description: 'Queue an invoice email to the client. Updates invoice status to "sent".'
)]
class SendInvoiceTool
{
    #[McpParameter('id', 'integer', 'The invoice ID', required: true)]
    public function handle(array $params): McpResponse
    {
        $invoice = Invoice::with('client')->findOrFail($params['id']);

        if ($invoice->status !== InvoiceStatus::Draft) {
            return McpResponse::error("Invoice has already been sent (status: {$invoice->status->value}).");
        }

        SendInvoiceEmail::dispatch($invoice);

        return McpResponse::success([
            'id' => $invoice->id,
            'number' => $invoice->number,
            'recipient' => $invoice->client->email,
            'message' => "Invoice {$invoice->number} queued for sending to {$invoice->client->email}.",
        ]);
    }
}
```

### Reminder Tools

#### list-reminders
```php
#[McpTool(
    name: 'list-reminders',
    description: 'List pending and upcoming reminders. Use to understand current priorities and follow-ups.'
)]
class ListRemindersTool
{
    #[McpParameter('filter', 'string', 'Filter: "due_today", "overdue", "upcoming", "all" (default: all)')]
    #[McpParameter('limit', 'integer', 'Maximum results (default: 25)')]
    public function handle(array $params): McpResponse
    {
        $query = Reminder::with('remindable')
            ->pending();

        match ($params['filter'] ?? 'all') {
            'due_today' => $query->dueToday(),
            'overdue' => $query->overdue(),
            'upcoming' => $query->upcoming(),
            default => null,
        };

        $reminders = $query->limit($params['limit'] ?? 25)->get();

        return McpResponse::success($reminders);
    }
}
```

#### create-reminder
```php
#[McpTool(
    name: 'create-reminder',
    description: 'Create a reminder attached to a client, project, or invoice.'
)]
class CreateReminderTool
{
    #[McpParameter('remindable_type', 'string', 'Entity type: "client", "project", or "invoice"', required: true)]
    #[McpParameter('remindable_id', 'integer', 'Entity ID', required: true)]
    #[McpParameter('title', 'string', 'Reminder title', required: true)]
    #[McpParameter('description', 'string', 'Additional details')]
    #[McpParameter('due_at', 'string', 'Due date (YYYY-MM-DD or relative like "+7 days")', required: true)]
    #[McpParameter('priority', 'string', 'Priority: "low", "normal", "high" (default: normal)')]
    public function handle(array $params): McpResponse
    {
        // Parse relative dates
        $dueAt = str_starts_with($params['due_at'], '+')
            ? now()->add($params['due_at'])
            : Carbon::parse($params['due_at']);

        $reminder = Reminder::create([
            'user_id' => auth()->id(),
            'remindable_type' => match($params['remindable_type']) {
                'client' => Client::class,
                'project' => Project::class,
                'invoice' => Invoice::class,
            },
            'remindable_id' => $params['remindable_id'],
            'title' => $params['title'],
            'description' => $params['description'] ?? null,
            'due_at' => $dueAt,
            'priority' => $params['priority'] ?? 'normal',
        ]);

        return McpResponse::success([
            'id' => $reminder->id,
            'title' => $reminder->title,
            'due_at' => $reminder->due_at->format('Y-m-d'),
            'message' => "Reminder created: '{$reminder->title}' due {$reminder->due_at->diffForHumans()}.",
        ]);
    }
}
```

#### complete-reminder
```php
#[McpTool(
    name: 'complete-reminder',
    description: 'Mark a reminder as completed.'
)]
class CompleteReminderTool
{
    #[McpParameter('id', 'integer', 'The reminder ID', required: true)]
    public function handle(array $params): McpResponse
    {
        $reminder = Reminder::findOrFail($params['id']);
        $reminder->complete();

        return McpResponse::success([
            'id' => $reminder->id,
            'title' => $reminder->title,
            'message' => "Reminder '{$reminder->title}' marked as completed.",
        ]);
    }
}
```

### Context Tools

#### get-stats
```php
#[McpTool(
    name: 'get-stats',
    description: 'Get dashboard statistics for context about current business state.'
)]
class GetStatsTool
{
    public function handle(array $params): McpResponse
    {
        return McpResponse::success([
            'revenue' => [
                'this_month' => Invoice::paid()->whereMonth('paid_at', now()->month)->sum('total'),
                'this_year' => Invoice::paid()->whereYear('paid_at', now()->year)->sum('total'),
            ],
            'invoices' => [
                'unpaid_count' => Invoice::unpaid()->count(),
                'unpaid_total' => Invoice::unpaid()->sum('total'),
                'overdue_count' => Invoice::overdue()->count(),
            ],
            'projects' => [
                'active_count' => Project::whereIn('status', ['in_progress', 'accepted'])->count(),
                'draft_offers_count' => Project::where('status', 'draft')->count(),
            ],
            'reminders' => [
                'due_today' => Reminder::pending()->dueToday()->count(),
                'overdue' => Reminder::pending()->overdue()->count(),
                'upcoming_week' => Reminder::pending()->upcoming(7)->count(),
            ],
        ]);
    }
}
```

#### validate-data
```php
#[McpTool(
    name: 'validate-data',
    description: 'Dry-run validation without persisting data. Use to check if data is valid before creating.'
)]
class ValidateDataTool
{
    #[McpParameter('resource', 'string', 'Resource type: "client", "project", "invoice", "reminder"', required: true)]
    #[McpParameter('data', 'object', 'The data to validate', required: true)]
    public function handle(array $params): McpResponse
    {
        $rules = match($params['resource']) {
            'client' => StoreClientRequest::rules(),
            'project' => StoreProjectRequest::rules(),
            'invoice' => StoreInvoiceRequest::rules(),
            'reminder' => StoreReminderRequest::rules(),
            default => throw new \InvalidArgumentException("Unknown resource: {$params['resource']}"),
        };

        $validator = Validator::make($params['data'], $rules);

        if ($validator->fails()) {
            return McpResponse::success([
                'valid' => false,
                'errors' => $validator->errors()->toArray(),
            ]);
        }

        return McpResponse::success([
            'valid' => true,
            'message' => 'Data is valid and can be created.',
        ]);
    }
}
```

## MCP Resources (Read-Only Context)

```php
// Resources expose data that Claude can read for context

resources:
  - uri: "crm://clients"
    name: "All Clients"
    description: "List of all clients with basic info"
    mimeType: "application/json"

  - uri: "crm://clients/{id}"
    name: "Client Details"
    description: "Full client information including projects and invoices"

  - uri: "crm://projects/recent"
    name: "Recent Projects"
    description: "Projects from the last 30 days"

  - uri: "crm://projects/{id}"
    name: "Project Details"
    description: "Full project with line items"

  - uri: "crm://stats"
    name: "Dashboard Statistics"
    description: "Revenue, pending invoices, reminders"
```

## Files to Create

```
app/Mcp/
├── Tools/
│   ├── Clients/
│   │   ├── ListClientsTool.php
│   │   ├── GetClientTool.php
│   │   └── CreateClientTool.php
│   ├── Projects/
│   │   ├── ListProjectsTool.php
│   │   ├── GetProjectTool.php
│   │   ├── CreateProjectTool.php
│   │   └── UpdateProjectStatusTool.php
│   ├── Invoices/
│   │   ├── CreateInvoiceFromProjectTool.php
│   │   └── SendInvoiceTool.php
│   ├── Reminders/
│   │   ├── ListRemindersTool.php
│   │   ├── CreateReminderTool.php
│   │   └── CompleteReminderTool.php
│   └── Context/
│       ├── GetStatsTool.php
│       └── ValidateDataTool.php
└── Resources/
    ├── ClientsResource.php
    ├── ProjectsResource.php
    └── StatsResource.php
```

## Claude Code Configuration

After implementation, users add to their Claude Code config:

```json
// ~/.claude/mcp_servers.json
{
  "reneweiser-crm": {
    "command": "php",
    "args": ["/path/to/crm/artisan", "mcp:serve"],
    "env": {
      "MCP_TOKEN": "your-sanctum-token-here"
    }
  }
}
```

## Example Prompts

### Use Case 1: Project from Notes
```
Read the file meeting-notes.txt and create a project in my CRM for the client mentioned.
Include appropriate line items based on the requirements discussed.
```

### Use Case 2: Strategic Offer
```
Get the details of project 42 and let's discuss how to structure the offer.
The client is price-sensitive, so we might want to propose a phased approach.
```

### Use Case 3: Daily Review
```
What's on my plate today? Show me due reminders, overdue invoices, and active projects.
```
