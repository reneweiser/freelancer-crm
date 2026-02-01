# MCP Client (Node.js)

## Overview

The MCP client is a lightweight Node.js application that runs **locally** on your machine. It translates Claude Code tool calls into HTTP requests to the remote CRM API.

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        Your Local Machine                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│   ┌──────────────┐     stdio      ┌──────────────────────────┐  │
│   │ Claude Code  │◄──────────────►│  MCP Client (Node.js)    │  │
│   └──────────────┘                │                          │  │
│                                   │  - Receives tool calls   │  │
│                                   │  - Makes HTTP requests   │  │
│                                   │  - Returns results       │  │
│                                   └────────────┬─────────────┘  │
│                                                │                 │
└────────────────────────────────────────────────┼─────────────────┘
                                                 │ HTTPS
                                                 ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Production Server                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│   ┌──────────────────────────────────────────────────────────┐  │
│   │                    REST API (/api/v1/*)                   │  │
│   │                                                           │  │
│   │   /clients, /projects, /invoices, /reminders, /stats     │  │
│   └──────────────────────────────────────────────────────────┘  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Project Structure

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

## Implementation

### package.json

```json
{
  "name": "reneweiser-crm-mcp",
  "version": "1.0.0",
  "type": "module",
  "main": "index.js",
  "dependencies": {
    "@modelcontextprotocol/sdk": "^1.0.0"
  }
}
```

### index.js (Main Server)

```javascript
#!/usr/bin/env node

import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";

import { ApiClient } from "./lib/api-client.js";
import { clientTools, handleClientTool } from "./tools/clients.js";
import { projectTools, handleProjectTool } from "./tools/projects.js";
import { invoiceTools, handleInvoiceTool } from "./tools/invoices.js";
import { reminderTools, handleReminderTool } from "./tools/reminders.js";
import { contextTools, handleContextTool } from "./tools/context.js";

// Initialize API client
const apiClient = new ApiClient(
  process.env.CRM_API_URL || "https://crm.example.com/api/v1",
  process.env.CRM_API_TOKEN
);

// Create MCP server
const server = new Server(
  { name: "reneweiser-crm", version: "1.0.0" },
  { capabilities: { tools: {} } }
);

// Combine all tools
const allTools = [
  ...clientTools,
  ...projectTools,
  ...invoiceTools,
  ...reminderTools,
  ...contextTools,
];

// List available tools
server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools: allTools,
}));

// Handle tool calls
server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  try {
    let result;

    if (name.startsWith("list-clients") || name.startsWith("get-client") || name.startsWith("create-client")) {
      result = await handleClientTool(apiClient, name, args);
    } else if (name.includes("project")) {
      result = await handleProjectTool(apiClient, name, args);
    } else if (name.includes("invoice")) {
      result = await handleInvoiceTool(apiClient, name, args);
    } else if (name.includes("reminder")) {
      result = await handleReminderTool(apiClient, name, args);
    } else {
      result = await handleContextTool(apiClient, name, args);
    }

    return {
      content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
    };
  } catch (error) {
    return {
      content: [{ type: "text", text: `Error: ${error.message}` }],
      isError: true,
    };
  }
});

// Start server
async function main() {
  const transport = new StdioServerTransport();
  await server.connect(transport);
  console.error("Reneweiser CRM MCP server running");
}

main().catch(console.error);
```

### lib/api-client.js

```javascript
export class ApiClient {
  constructor(baseUrl, token) {
    this.baseUrl = baseUrl;
    this.token = token;
  }

  async request(method, path, data = null) {
    const url = `${this.baseUrl}${path}`;

    const options = {
      method,
      headers: {
        "Authorization": `Bearer ${this.token}`,
        "Accept": "application/json",
        "Content-Type": "application/json",
      },
    };

    if (data && (method === "POST" || method === "PUT")) {
      options.body = JSON.stringify(data);
    }

    const response = await fetch(url, options);
    const json = await response.json();

    if (!response.ok) {
      throw new Error(json.error?.message || `API error: ${response.status}`);
    }

    return json.data;
  }

  // Convenience methods
  get(path) { return this.request("GET", path); }
  post(path, data) { return this.request("POST", path, data); }
  put(path, data) { return this.request("PUT", path, data); }
  delete(path) { return this.request("DELETE", path); }
}
```

### tools/clients.js

```javascript
export const clientTools = [
  {
    name: "list-clients",
    description: "Search and list clients in the CRM. Use this to find existing clients before creating projects or invoices.",
    inputSchema: {
      type: "object",
      properties: {
        search: {
          type: "string",
          description: "Search by company name, contact name, or email",
        },
        type: {
          type: "string",
          enum: ["company", "individual"],
          description: "Filter by client type",
        },
        limit: {
          type: "integer",
          description: "Maximum results to return (default: 25)",
        },
      },
    },
  },
  {
    name: "get-client",
    description: "Get detailed information about a specific client including their projects and invoices.",
    inputSchema: {
      type: "object",
      properties: {
        id: {
          type: "integer",
          description: "The client ID",
        },
      },
      required: ["id"],
    },
  },
  {
    name: "create-client",
    description: "Create a new client in the CRM.",
    inputSchema: {
      type: "object",
      properties: {
        type: {
          type: "string",
          enum: ["company", "individual"],
          description: "Client type",
        },
        company_name: {
          type: "string",
          description: "Company name (required for company type)",
        },
        contact_name: {
          type: "string",
          description: "Contact person name",
        },
        email: {
          type: "string",
          description: "Email address",
        },
        phone: {
          type: "string",
          description: "Phone number",
        },
        street: {
          type: "string",
          description: "Street address",
        },
        zip: {
          type: "string",
          description: "Postal code",
        },
        city: {
          type: "string",
          description: "City",
        },
        country: {
          type: "string",
          description: "Country code (default: DE)",
        },
        vat_id: {
          type: "string",
          description: "VAT ID for companies",
        },
      },
      required: ["type"],
    },
  },
];

export async function handleClientTool(api, name, args) {
  switch (name) {
    case "list-clients": {
      const params = new URLSearchParams();
      if (args.search) params.set("search", args.search);
      if (args.type) params.set("type", args.type);
      if (args.limit) params.set("per_page", args.limit);

      const query = params.toString();
      return api.get(`/clients${query ? `?${query}` : ""}`);
    }

    case "get-client":
      return api.get(`/clients/${args.id}`);

    case "create-client":
      return api.post("/clients", args);

    default:
      throw new Error(`Unknown client tool: ${name}`);
  }
}
```

### tools/projects.js

```javascript
export const projectTools = [
  {
    name: "list-projects",
    description: "List projects/offers with optional filters. Use to find existing projects for context or invoicing.",
    inputSchema: {
      type: "object",
      properties: {
        client_id: {
          type: "integer",
          description: "Filter by client ID",
        },
        status: {
          type: "string",
          enum: ["draft", "sent", "accepted", "declined", "in_progress", "completed", "cancelled"],
          description: "Filter by status",
        },
        search: {
          type: "string",
          description: "Search in title and description",
        },
        limit: {
          type: "integer",
          description: "Maximum results (default: 25)",
        },
      },
    },
  },
  {
    name: "get-project",
    description: "Get detailed project information including all line items. Use for context when strategizing offers.",
    inputSchema: {
      type: "object",
      properties: {
        id: {
          type: "integer",
          description: "The project ID",
        },
      },
      required: ["id"],
    },
  },
  {
    name: "create-project",
    description: "Create a new project/offer with line items. Use after strategizing with the user about scope and pricing.",
    inputSchema: {
      type: "object",
      properties: {
        client_id: {
          type: "integer",
          description: "ID of the existing client",
        },
        title: {
          type: "string",
          description: "Project title",
        },
        description: {
          type: "string",
          description: "Detailed project description",
        },
        type: {
          type: "string",
          enum: ["hourly", "fixed"],
          description: "Project type (default: fixed)",
        },
        valid_until: {
          type: "string",
          description: "Offer validity date (YYYY-MM-DD)",
        },
        items: {
          type: "array",
          description: "Line items",
          items: {
            type: "object",
            properties: {
              description: { type: "string" },
              quantity: { type: "number" },
              unit: { type: "string" },
              unit_price: { type: "number" },
            },
            required: ["description", "unit_price"],
          },
        },
      },
      required: ["client_id", "title", "items"],
    },
  },
  {
    name: "update-project-status",
    description: "Change the status of a project (draft → sent → accepted → in_progress → completed).",
    inputSchema: {
      type: "object",
      properties: {
        id: {
          type: "integer",
          description: "The project ID",
        },
        status: {
          type: "string",
          enum: ["draft", "sent", "accepted", "declined", "in_progress", "completed", "cancelled"],
          description: "New status",
        },
      },
      required: ["id", "status"],
    },
  },
];

export async function handleProjectTool(api, name, args) {
  switch (name) {
    case "list-projects": {
      const params = new URLSearchParams();
      if (args.client_id) params.set("client_id", args.client_id);
      if (args.status) params.set("status", args.status);
      if (args.search) params.set("search", args.search);
      if (args.limit) params.set("per_page", args.limit);

      const query = params.toString();
      return api.get(`/projects${query ? `?${query}` : ""}`);
    }

    case "get-project":
      return api.get(`/projects/${args.id}`);

    case "create-project":
      return api.post("/projects", args);

    case "update-project-status":
      return api.put(`/projects/${args.id}`, { status: args.status });

    default:
      throw new Error(`Unknown project tool: ${name}`);
  }
}
```

### tools/invoices.js

```javascript
export const invoiceTools = [
  {
    name: "create-invoice-from-project",
    description: "Generate an invoice from an existing project. Copies all project items to the invoice.",
    inputSchema: {
      type: "object",
      properties: {
        project_id: {
          type: "integer",
          description: "The project ID to invoice",
        },
      },
      required: ["project_id"],
    },
  },
  {
    name: "send-invoice",
    description: "Queue an invoice email to the client. Updates invoice status to 'sent'.",
    inputSchema: {
      type: "object",
      properties: {
        id: {
          type: "integer",
          description: "The invoice ID",
        },
      },
      required: ["id"],
    },
  },
];

export async function handleInvoiceTool(api, name, args) {
  switch (name) {
    case "create-invoice-from-project":
      return api.post(`/invoices/from-project/${args.project_id}`);

    case "send-invoice":
      return api.post(`/invoices/${args.id}/send`);

    default:
      throw new Error(`Unknown invoice tool: ${name}`);
  }
}
```

### tools/reminders.js

```javascript
export const reminderTools = [
  {
    name: "list-reminders",
    description: "List pending and upcoming reminders. Use to understand current priorities and follow-ups.",
    inputSchema: {
      type: "object",
      properties: {
        filter: {
          type: "string",
          enum: ["due_today", "overdue", "upcoming", "all"],
          description: "Filter reminders (default: all)",
        },
        limit: {
          type: "integer",
          description: "Maximum results (default: 25)",
        },
      },
    },
  },
  {
    name: "create-reminder",
    description: "Create a reminder attached to a client, project, or invoice.",
    inputSchema: {
      type: "object",
      properties: {
        remindable_type: {
          type: "string",
          enum: ["client", "project", "invoice"],
          description: "Entity type to attach reminder to",
        },
        remindable_id: {
          type: "integer",
          description: "Entity ID",
        },
        title: {
          type: "string",
          description: "Reminder title",
        },
        description: {
          type: "string",
          description: "Additional details",
        },
        due_at: {
          type: "string",
          description: "Due date (YYYY-MM-DD or relative like '+7 days')",
        },
        priority: {
          type: "string",
          enum: ["low", "normal", "high"],
          description: "Priority (default: normal)",
        },
      },
      required: ["remindable_type", "remindable_id", "title", "due_at"],
    },
  },
  {
    name: "complete-reminder",
    description: "Mark a reminder as completed.",
    inputSchema: {
      type: "object",
      properties: {
        id: {
          type: "integer",
          description: "The reminder ID",
        },
      },
      required: ["id"],
    },
  },
];

export async function handleReminderTool(api, name, args) {
  switch (name) {
    case "list-reminders": {
      const params = new URLSearchParams();
      if (args.filter) params.set("filter", args.filter);
      if (args.limit) params.set("per_page", args.limit);

      const query = params.toString();
      return api.get(`/reminders${query ? `?${query}` : ""}`);
    }

    case "create-reminder":
      return api.post("/reminders", args);

    case "complete-reminder":
      return api.post(`/reminders/${args.id}/complete`);

    default:
      throw new Error(`Unknown reminder tool: ${name}`);
  }
}
```

### tools/context.js

```javascript
export const contextTools = [
  {
    name: "get-stats",
    description: "Get dashboard statistics for context about current business state.",
    inputSchema: {
      type: "object",
      properties: {},
    },
  },
  {
    name: "validate-data",
    description: "Dry-run validation without persisting data. Use to check if data is valid before creating.",
    inputSchema: {
      type: "object",
      properties: {
        resource: {
          type: "string",
          enum: ["client", "project", "invoice", "reminder"],
          description: "Resource type to validate",
        },
        data: {
          type: "object",
          description: "The data to validate",
        },
      },
      required: ["resource", "data"],
    },
  },
];

export async function handleContextTool(api, name, args) {
  switch (name) {
    case "get-stats":
      return api.get("/stats");

    case "validate-data":
      return api.post("/ai/validate", args);

    default:
      throw new Error(`Unknown context tool: ${name}`);
  }
}
```

## Installation & Setup

### 1. Create the MCP Server Directory

```bash
mkdir -p ~/.claude/mcp-servers/reneweiser-crm
cd ~/.claude/mcp-servers/reneweiser-crm
```

### 2. Initialize and Install Dependencies

```bash
npm init -y
npm install @modelcontextprotocol/sdk
```

### 3. Copy the Source Files

Copy the files from this document into the directory structure.

### 4. Configure Claude Code

Edit `~/.claude/mcp_servers.json`:

```json
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

### 5. Test the Setup

Restart Claude Code and try:
```
What clients do I have in my CRM?
```

## Security Notes

- Store the API token securely (not in version control)
- Consider using environment variables or a secrets manager
- The token should be a dedicated token, not your main account token
- Revoke and regenerate tokens periodically

## Troubleshooting

### MCP Server Not Starting
- Check that Node.js is installed: `node --version`
- Verify the path in `mcp_servers.json` is correct
- Check Claude Code logs for errors

### Authentication Errors
- Verify the API token is correct
- Check that the API URL is accessible
- Test with curl: `curl -H "Authorization: Bearer $TOKEN" $API_URL/clients`

### Tool Calls Failing
- Check the Laravel API logs for errors
- Verify the API endpoints are implemented
- Test endpoints directly with curl or Postman
