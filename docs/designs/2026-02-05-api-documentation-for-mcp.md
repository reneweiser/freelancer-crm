# Freelancer CRM REST API v1 Documentation

Complete API specification for building an MCP (Model Context Protocol) server that integrates with the Freelancer CRM.

## Overview

**Base URL:** `/api/v1`

**Authentication:** Bearer token (Laravel Sanctum)
```
Authorization: Bearer {sanctum-token}
```

**Rate Limit:** 60 requests per minute per token

**Content Type:** `application/json`

---

## Response Formats

### Success Response
```json
{
  "success": true,
  "data": { ... }
}
```

### Paginated Response
```json
{
  "success": true,
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 73
  },
  "links": {
    "first": "https://crm.example.com/api/v1/clients?page=1",
    "last": "https://crm.example.com/api/v1/clients?page=5",
    "prev": null,
    "next": "https://crm.example.com/api/v1/clients?page=2"
  }
}
```

### Error Response
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The input data is invalid.",
    "suggestions": [
      "Client type is required (company or individual).",
      "Use GET /api/v1/clients to retrieve existing clients."
    ]
  }
}
```

### Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `VALIDATION_ERROR` | 422 | Input validation failed |
| `NOT_FOUND` | 404 | Resource not found |
| `UNAUTHORIZED` | 401 | Missing or invalid token |
| `FORBIDDEN` | 403 | No permission for action |
| `RATE_LIMITED` | 429 | Too many requests |
| `SERVER_ERROR` | 500 | Internal server error |
| `CLIENT_HAS_RELATIONS` | 422 | Cannot delete client with existing projects/invoices |
| `PROJECT_HAS_INVOICES` | 422 | Cannot delete project with existing invoices |
| `INVALID_STATUS` | 422 | Invalid status value |
| `INVALID_TRANSITION` | 422 | Status transition not allowed |
| `INVOICE_NOT_DRAFT` | 422 | Only draft invoices can be updated |
| `CANNOT_DELETE_INVOICE` | 422 | Only draft invoices can be deleted |
| `PROJECT_CANNOT_BE_INVOICED` | 422 | Project status doesn't allow invoicing |
| `ALREADY_PAID` | 422 | Invoice already marked as paid |
| `INVOICE_NOT_SENT` | 422 | Cannot mark draft invoice as paid |
| `INVOICE_CANCELLED` | 422 | Cannot mark cancelled invoice as paid |
| `REMINDABLE_NOT_FOUND` | 404 | Referenced entity not found |
| `SYSTEM_REMINDER` | 422 | System reminders cannot be updated |
| `ALREADY_COMPLETED` | 422 | Reminder already completed |
| `BATCH_FAILED` | 422 | Batch operation failed (all rolled back) |

---

## Enumeration Values

### ClientType
| Value | Description |
|-------|-------------|
| `company` | Business/Company client |
| `individual` | Individual/Private person |

### ProjectType
| Value | Description |
|-------|-------------|
| `fixed` | Fixed-price project |
| `hourly` | Time-and-materials project |

### ProjectStatus
| Value | Description | Allowed Transitions |
|-------|-------------|---------------------|
| `draft` | Initial state, not sent to client | `sent`, `cancelled` |
| `sent` | Offer sent to client | `accepted`, `declined`, `cancelled` |
| `accepted` | Client accepted the offer | `in_progress`, `cancelled` |
| `declined` | Client declined the offer | (none - terminal) |
| `in_progress` | Work in progress | `completed`, `cancelled` |
| `completed` | Project finished | `in_progress` (reopen) |
| `cancelled` | Project cancelled | (none - terminal) |

### InvoiceStatus
| Value | Description | Allowed Transitions |
|-------|-------------|---------------------|
| `draft` | Initial state, not sent | `sent`, `cancelled` |
| `sent` | Invoice sent to client | `paid`, `overdue`, `cancelled` |
| `overdue` | Payment past due date | `paid`, `cancelled` |
| `paid` | Payment received | (none - terminal) |
| `cancelled` | Invoice cancelled | (none - terminal) |

### ReminderPriority
| Value | Description |
|-------|-------------|
| `low` | Low priority |
| `normal` | Normal priority (default) |
| `high` | High priority |

### ReminderRecurrence
| Value | Description |
|-------|-------------|
| `daily` | Repeats every day |
| `weekly` | Repeats every week |
| `monthly` | Repeats every month |
| `yearly` | Repeats every year |

### RemindableType
| Value | Description |
|-------|-------------|
| `Client` | Attached to a client |
| `Project` | Attached to a project |
| `Invoice` | Attached to an invoice |

---

## Clients API

### List Clients

```
GET /api/v1/clients
```

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `search` | string | No | Search by company_name, contact_name, or email |
| `type` | string | No | Filter by client type: `company` or `individual` |
| `per_page` | integer | No | Items per page (default: 15, max: 100) |
| `page` | integer | No | Page number |

**Response:** Paginated list of `ClientResource`

**Example Request:**
```bash
GET /api/v1/clients?search=GmbH&type=company&per_page=25
```

---

### Get Client

```
GET /api/v1/clients/{id}
```

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Client ID |

**Response:** Single `ClientResource`

---

### Create Client

```
POST /api/v1/clients
```

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | string | Yes | `company` or `individual` |
| `company_name` | string | No | Company name (recommended for type=company) |
| `vat_id` | string | No | VAT identification number (max 50 chars) |
| `contact_name` | string | Yes | Contact person name (max 255 chars) |
| `email` | string | Yes | Email address |
| `phone` | string | No | Phone number (max 50 chars) |
| `street` | string | No | Street address (max 255 chars) |
| `postal_code` | string | No | Postal/ZIP code (max 20 chars) |
| `city` | string | No | City name (max 255 chars) |
| `country` | string | No | ISO 3166-1 alpha-2 country code (2 chars) |
| `notes` | string | No | Internal notes |

**Response:** Created `ClientResource` (HTTP 201)

**Example Request:**
```json
{
  "type": "company",
  "company_name": "Acme GmbH",
  "contact_name": "Max Mustermann",
  "email": "max@acme.de",
  "phone": "+49 123 456789",
  "street": "Hauptstr. 1",
  "postal_code": "10115",
  "city": "Berlin",
  "country": "DE"
}
```

---

### Update Client

```
PUT /api/v1/clients/{id}
PATCH /api/v1/clients/{id}
```

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Client ID |

**Request Body:** Same as Create Client (all fields optional)

**Response:** Updated `ClientResource`

---

### Delete Client

```
DELETE /api/v1/clients/{id}
```

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Client ID |

**Response:**
```json
{
  "success": true,
  "data": { "deleted": true }
}
```

**Error:** Returns `CLIENT_HAS_RELATIONS` if client has projects or invoices.

---

### ClientResource Schema

```json
{
  "id": 1,
  "type": "company",
  "type_label": "Unternehmen",
  "company_name": "Acme GmbH",
  "vat_id": "DE123456789",
  "contact_name": "Max Mustermann",
  "email": "max@acme.de",
  "phone": "+49 123 456789",
  "street": "Hauptstr. 1",
  "postal_code": "10115",
  "city": "Berlin",
  "country": "DE",
  "notes": "Important client",
  "display_name": "Acme GmbH",
  "full_address": "Hauptstr. 1, 10115 Berlin, DE",
  "created_at": "2026-01-15T10:30:00+00:00",
  "updated_at": "2026-02-01T14:22:00+00:00",
  "projects_count": 5,
  "invoices_count": 12
}
```

---

## Projects API

### List Projects

```
GET /api/v1/projects
```

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `search` | string | No | Search by title, reference, or description |
| `status` | string | No | Filter by status (see ProjectStatus enum) |
| `client_id` | integer | No | Filter by client ID |
| `type` | string | No | Filter by type: `fixed` or `hourly` |
| `per_page` | integer | No | Items per page (default: 15, max: 100) |
| `page` | integer | No | Page number |

**Response:** Paginated list of `ProjectResource` (includes client and items)

---

### Get Project

```
GET /api/v1/projects/{id}
```

**Response:** Single `ProjectResource` with client and items

---

### Create Project

```
POST /api/v1/projects
```

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `client_id` | integer | Yes | Client ID (must exist and belong to user) |
| `title` | string | Yes | Project title (max 255 chars) |
| `description` | string | No | Project description |
| `reference` | string | No | External reference number (max 50 chars) |
| `type` | string | Yes | `fixed` or `hourly` |
| `hourly_rate` | decimal | Conditional | Required if type=hourly |
| `fixed_price` | decimal | Conditional | Required if type=fixed |
| `offer_date` | date | No | Offer creation date (YYYY-MM-DD) |
| `offer_valid_until` | date | No | Offer expiration date (must be >= offer_date) |
| `start_date` | date | No | Project start date |
| `end_date` | date | No | Project end date (must be >= start_date) |
| `notes` | string | No | Internal notes |
| `items` | array | No | Line items (see below) |

**Item Object:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `description` | string | Yes | Item description (max 500 chars) |
| `quantity` | decimal | Yes | Quantity (min: 0) |
| `unit` | string | No | Unit of measure (max 50 chars) |
| `unit_price` | decimal | Yes | Price per unit (min: 0) |

**Note:** Projects are always created with status `draft`.

**Response:** Created `ProjectResource` (HTTP 201)

**Example Request:**
```json
{
  "client_id": 1,
  "title": "Website Redesign",
  "description": "Complete redesign of corporate website",
  "type": "fixed",
  "fixed_price": 5000.00,
  "offer_date": "2026-02-05",
  "offer_valid_until": "2026-03-05",
  "items": [
    {
      "description": "Design & Konzeption",
      "quantity": 1,
      "unit": "pauschal",
      "unit_price": 2000.00
    },
    {
      "description": "Frontend-Entwicklung",
      "quantity": 30,
      "unit": "Stunden",
      "unit_price": 100.00
    }
  ]
}
```

---

### Update Project

```
PUT /api/v1/projects/{id}
PATCH /api/v1/projects/{id}
```

**Request Body:** Same as Create Project (all fields optional)

When updating `items`:
- Items with `id` field are updated
- Items without `id` are created
- Items not included in the array are deleted

**Response:** Updated `ProjectResource`

---

### Delete Project

```
DELETE /api/v1/projects/{id}
```

**Error:** Returns `PROJECT_HAS_INVOICES` if project has related invoices.

---

### Transition Project Status

```
POST /api/v1/projects/{id}/transition
```

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `status` | string | Yes | Target status (see ProjectStatus enum) |
| `start_date` | date | No | For transitioning to `in_progress` |
| `end_date` | date | No | For transitioning to `completed` |

**Response:** Updated `ProjectResource`

**Error Codes:**
- `INVALID_STATUS`: The status value doesn't exist
- `INVALID_TRANSITION`: The transition isn't allowed from current status

**Example Request:**
```json
{
  "status": "in_progress",
  "start_date": "2026-02-10"
}
```

---

### ProjectResource Schema

```json
{
  "id": 1,
  "client_id": 1,
  "title": "Website Redesign",
  "description": "Complete redesign of corporate website",
  "reference": "WEB-2026-001",
  "type": "fixed",
  "type_label": "Festpreis",
  "hourly_rate": null,
  "fixed_price": 5000.00,
  "status": "in_progress",
  "status_label": "In Bearbeitung",
  "status_color": "warning",
  "allowed_transitions": ["completed", "cancelled"],
  "offer_date": "2026-02-05",
  "offer_valid_until": "2026-03-05",
  "offer_sent_at": "2026-02-06T09:15:00+00:00",
  "offer_accepted_at": "2026-02-08T14:30:00+00:00",
  "start_date": "2026-02-10",
  "end_date": null,
  "notes": "Priority client",
  "total_value": 5000.00,
  "total_hours": 30.0,
  "billable_hours": 25.5,
  "unbilled_hours": 4.5,
  "unbilled_amount": 450.00,
  "can_be_invoiced": true,
  "created_at": "2026-02-05T10:00:00+00:00",
  "updated_at": "2026-02-10T08:00:00+00:00",
  "client": { /* ClientResource */ },
  "items": [
    {
      "id": 1,
      "description": "Design & Konzeption",
      "quantity": 1.0,
      "unit": "pauschal",
      "unit_price": 2000.00,
      "position": 1,
      "total": 2000.00
    }
  ]
}
```

---

## Invoices API

### List Invoices

```
GET /api/v1/invoices
```

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `search` | string | No | Search by invoice number or client name |
| `status` | string | No | Filter by status (see InvoiceStatus enum) |
| `client_id` | integer | No | Filter by client ID |
| `project_id` | integer | No | Filter by project ID |
| `year` | integer | No | Filter by issue year |
| `per_page` | integer | No | Items per page (default: 15, max: 100) |
| `page` | integer | No | Page number |

**Response:** Paginated list of `InvoiceResource`

---

### Get Invoice

```
GET /api/v1/invoices/{id}
```

**Response:** Single `InvoiceResource` with client, project (if linked), and items

---

### Create Invoice

```
POST /api/v1/invoices
```

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `client_id` | integer | Yes | Client ID |
| `project_id` | integer | No | Link to project |
| `issued_at` | date | No | Issue date (default: today) |
| `due_at` | date | No | Due date (default: issued_at + payment_terms_days from settings) |
| `vat_rate` | decimal | No | VAT rate % (default: from settings, typically 19%) |
| `service_period_start` | date | No | Service period start |
| `service_period_end` | date | No | Service period end (must be >= start) |
| `notes` | string | No | Notes to display on invoice |
| `footer_text` | string | No | Footer text (default: from settings) |
| `items` | array | Yes | At least one line item (see below) |

**Item Object:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `description` | string | Yes | Item description (max 500 chars) |
| `quantity` | decimal | Yes | Quantity (min: 0) |
| `unit` | string | No | Unit of measure (max 50 chars) |
| `unit_price` | decimal | Yes | Price per unit (min: 0) |
| `vat_rate` | decimal | No | Item-specific VAT rate (default: invoice vat_rate) |

**Note:** Invoice number is auto-generated in `YYYY-NNN` format.

**Response:** Created `InvoiceResource` (HTTP 201)

**Example Request:**
```json
{
  "client_id": 1,
  "project_id": 5,
  "issued_at": "2026-02-15",
  "due_at": "2026-03-01",
  "vat_rate": 19,
  "service_period_start": "2026-01-01",
  "service_period_end": "2026-01-31",
  "items": [
    {
      "description": "Website-Entwicklung",
      "quantity": 40,
      "unit": "Stunden",
      "unit_price": 95.00
    },
    {
      "description": "Hosting Setup",
      "quantity": 1,
      "unit": "pauschal",
      "unit_price": 150.00
    }
  ]
}
```

---

### Update Invoice

```
PUT /api/v1/invoices/{id}
PATCH /api/v1/invoices/{id}
```

**Restrictions:** Only `draft` invoices can be updated.

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `issued_at` | date | No | Issue date |
| `due_at` | date | No | Due date |
| `vat_rate` | decimal | No | VAT rate % |
| `service_period_start` | date | No | Service period start |
| `service_period_end` | date | No | Service period end |
| `notes` | string | No | Notes |
| `footer_text` | string | No | Footer text |
| `items` | array | No | Line items (replaces all if provided) |

When updating `items`:
- Items with `id` field are updated
- Items without `id` are created
- Items not included are deleted

**Response:** Updated `InvoiceResource`

**Error:** Returns `INVOICE_NOT_DRAFT` if invoice is not in draft status.

---

### Delete Invoice

```
DELETE /api/v1/invoices/{id}
```

**Restrictions:** Only `draft` invoices can be deleted.

**Error:** Returns `CANNOT_DELETE_INVOICE` if invoice is not in draft status.

---

### Create Invoice from Project

```
POST /api/v1/invoices/from-project
```

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `project_id` | integer | Yes | Project ID |

**Requirements:** Project must be in status `accepted`, `in_progress`, or `completed`.

**Response:** Created `InvoiceResource` (HTTP 201)

**Error:** Returns `PROJECT_CANNOT_BE_INVOICED` if project status doesn't allow invoicing.

---

### Mark Invoice as Paid

```
POST /api/v1/invoices/{id}/mark-paid
```

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `paid_at` | date | No | Payment date (default: today) |
| `payment_method` | string | No | Payment method (max 100 chars) |

**Requirements:** Invoice must be in status `sent` or `overdue`.

**Response:** Updated `InvoiceResource`

**Errors:**
- `ALREADY_PAID`: Invoice is already paid
- `INVOICE_NOT_SENT`: Invoice is still a draft
- `INVOICE_CANCELLED`: Invoice was cancelled

---

### InvoiceResource Schema

```json
{
  "id": 1,
  "client_id": 1,
  "project_id": 5,
  "number": "2026-001",
  "status": "sent",
  "status_label": "Gesendet",
  "status_color": "info",
  "allowed_transitions": ["paid", "overdue", "cancelled"],
  "issued_at": "2026-02-15",
  "due_at": "2026-03-01",
  "paid_at": null,
  "payment_method": null,
  "subtotal": 3950.00,
  "vat_rate": 19.00,
  "vat_amount": 750.50,
  "total": 4700.50,
  "formatted_total": "4.700,50 EUR",
  "service_period_start": "2026-01-01",
  "service_period_end": "2026-01-31",
  "notes": "Thank you for your business",
  "footer_text": "Payment within 14 days",
  "created_at": "2026-02-15T09:00:00+00:00",
  "updated_at": "2026-02-15T09:30:00+00:00",
  "client": { /* ClientResource */ },
  "project": { /* ProjectResource */ },
  "items": [
    {
      "id": 1,
      "description": "Website-Entwicklung",
      "quantity": 40.0,
      "unit": "Stunden",
      "unit_price": 95.00,
      "vat_rate": 19.00,
      "position": 1,
      "total": 3800.00,
      "vat_amount": 722.00,
      "gross_total": 4522.00
    }
  ]
}
```

---

## Reminders API

### List Reminders

```
GET /api/v1/reminders
```

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `search` | string | No | Search by title or description |
| `priority` | string | No | Filter by priority: `low`, `normal`, `high` |
| `status` | string | No | Filter: `pending`, `completed`, `overdue`, `due` |
| `remindable_type` | string | No | Filter by type: `Client`, `Project`, `Invoice` |
| `remindable_id` | integer | No | Filter by attached entity ID |
| `upcoming_days` | integer | No | Show reminders due within N days |
| `per_page` | integer | No | Items per page (default: 15, max: 100) |
| `page` | integer | No | Page number |

**Response:** Paginated list of `ReminderResource` (ordered by due_at)

---

### Get Reminder

```
GET /api/v1/reminders/{id}
```

**Response:** Single `ReminderResource` with remindable entity

---

### Create Reminder

```
POST /api/v1/reminders
```

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | Yes | Reminder title (max 255 chars) |
| `description` | string | No | Detailed description |
| `due_at` | datetime | Yes | Due date/time (ISO 8601) |
| `priority` | string | No | `low`, `normal` (default), or `high` |
| `recurrence` | string | No | `daily`, `weekly`, `monthly`, `yearly` |
| `remindable_type` | string | No | `Client`, `Project`, or `Invoice` |
| `remindable_id` | integer | Conditional | Required if remindable_type is set |

**Response:** Created `ReminderResource` (HTTP 201)

**Example Request:**
```json
{
  "title": "Follow up with client",
  "description": "Discuss project progress",
  "due_at": "2026-02-10T10:00:00+00:00",
  "priority": "high",
  "remindable_type": "Client",
  "remindable_id": 1
}
```

---

### Update Reminder

```
PUT /api/v1/reminders/{id}
PATCH /api/v1/reminders/{id}
```

**Restrictions:** System-generated reminders cannot be updated.

**Request Body:** Same as Create Reminder (all fields optional)

**Response:** Updated `ReminderResource`

**Error:** Returns `SYSTEM_REMINDER` if attempting to update a system reminder.

---

### Delete Reminder

```
DELETE /api/v1/reminders/{id}
```

---

### Complete Reminder

```
POST /api/v1/reminders/{id}/complete
```

**Response:**
- For non-recurring: Updated `ReminderResource` with `completed_at` set
- For recurring: Object with `completed` (the completed reminder) and `next_occurrence` (the new reminder)

```json
{
  "success": true,
  "data": {
    "completed": { /* ReminderResource */ },
    "next_occurrence": { /* ReminderResource */ }
  }
}
```

**Error:** Returns `ALREADY_COMPLETED` if reminder is already completed.

---

### Snooze Reminder

```
POST /api/v1/reminders/{id}/snooze
```

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `hours` | integer | No | Hours to snooze (default: 24, max: 720) |

**Response:** Updated `ReminderResource` with `snoozed_until` set

**Error:** Returns `ALREADY_COMPLETED` if reminder is already completed.

---

### ReminderResource Schema

```json
{
  "id": 1,
  "title": "Follow up with client",
  "description": "Discuss project progress",
  "due_at": "2026-02-10T10:00:00+00:00",
  "snoozed_until": null,
  "completed_at": null,
  "notified_at": null,
  "recurrence": "weekly",
  "recurrence_label": "Wöchentlich",
  "priority": "high",
  "priority_label": "Hoch",
  "priority_color": "danger",
  "is_system": false,
  "system_type": null,
  "is_overdue": false,
  "is_due_today": true,
  "effective_due_at": "2026-02-10T10:00:00+00:00",
  "remindable_type": "Client",
  "remindable_id": 1,
  "created_at": "2026-02-05T08:00:00+00:00",
  "updated_at": "2026-02-05T08:00:00+00:00",
  "remindable": { /* ClientResource/ProjectResource/InvoiceResource */ }
}
```

---

## Stats API

### Get Dashboard Statistics

```
GET /api/v1/stats
```

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `year` | integer | No | Year for revenue stats (default: current year) |

**Response:**
```json
{
  "success": true,
  "data": {
    "revenue": {
      "total_year": 125000.00,
      "outstanding": 8500.00,
      "monthly": [
        { "month": 1, "label": "Jan", "total": 12000.00 },
        { "month": 2, "label": "Feb", "total": 15000.00 },
        ...
      ]
    },
    "projects": {
      "total": 42,
      "by_status": {
        "draft": 3,
        "sent": 5,
        "accepted": 2,
        "in_progress": 8,
        "completed": 20,
        "declined": 2,
        "cancelled": 2
      },
      "active": 10,
      "offers_pending": 5
    },
    "invoices": {
      "total_year": 38,
      "by_status": {
        "draft": 2,
        "sent": 5,
        "paid": 28,
        "overdue": 2,
        "cancelled": 1
      },
      "overdue_amount": 3200.00
    },
    "reminders": {
      "total_pending": 12,
      "overdue": 3,
      "due_today": 2,
      "upcoming_7_days": 8,
      "by_priority": {
        "high": 4,
        "normal": 6,
        "low": 2
      }
    },
    "year": 2026,
    "generated_at": "2026-02-05T14:30:00+00:00"
  }
}
```

---

## AI Helper APIs

### Batch Operations

```
POST /api/v1/batch
```

Execute multiple operations in a single atomic transaction. If any operation fails, all operations are rolled back.

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `operations` | array | Yes | Array of operations (min: 1, max: 50) |

**Operation Object:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `action` | string | Yes | Operation action (see below) |
| `resource` | string | Yes | Resource type: `client(s)`, `project(s)`, `invoice(s)`, `reminder(s)` |
| `data` | object | Conditional | Data for create/update operations |
| `id` | mixed | Conditional | Resource ID for update/delete operations |

**Available Actions by Resource:**

| Resource | Actions |
|----------|---------|
| `client` / `clients` | `create`, `update`, `delete` |
| `project` / `projects` | `create`, `update`, `delete`, `transition` |
| `invoice` / `invoices` | `create`, `from_project`, `mark_paid`, `delete` |
| `reminder` / `reminders` | `create`, `update`, `delete`, `complete`, `snooze` |

**Reference System:**
Use `$ref:` prefix to reference IDs from previous operations in the batch.

Add `$ref` field to operation data to create a named reference:
```json
{
  "action": "create",
  "resource": "client",
  "data": {
    "$ref": "new_client",
    "type": "company",
    "company_name": "New Corp",
    "contact_name": "John",
    "email": "john@new.com"
  }
}
```

Reference it in subsequent operations:
```json
{
  "action": "create",
  "resource": "project",
  "data": {
    "client_id": "$ref:new_client",
    "title": "New Project",
    "type": "fixed"
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "batch_id": "batch_65d8f3a2b1c4d",
    "total": 3,
    "succeeded": 3,
    "failed": 0,
    "results": [
      {
        "index": 0,
        "success": true,
        "data": { "id": 15, "type": "client" },
        "ref": "new_client"
      },
      {
        "index": 1,
        "success": true,
        "data": { "id": 42, "type": "project" },
        "ref": null
      }
    ]
  }
}
```

**Error Response (batch failure):**
```json
{
  "success": false,
  "error": {
    "code": "BATCH_FAILED",
    "message": "Client with ID 999 does not exist.",
    "suggestions": [
      "All operations have been rolled back.",
      "Fix the error and retry the entire batch."
    ]
  }
}
```

**Example - Create Client with Project and Reminder:**
```json
{
  "operations": [
    {
      "action": "create",
      "resource": "client",
      "data": {
        "$ref": "new_client",
        "type": "company",
        "company_name": "Tech Solutions GmbH",
        "contact_name": "Anna Schmidt",
        "email": "anna@techsolutions.de"
      }
    },
    {
      "action": "create",
      "resource": "project",
      "data": {
        "$ref": "new_project",
        "client_id": "$ref:new_client",
        "title": "E-Commerce Platform",
        "type": "fixed",
        "fixed_price": 25000,
        "items": [
          { "description": "Frontend Development", "quantity": 1, "unit_price": 15000 },
          { "description": "Backend Development", "quantity": 1, "unit_price": 10000 }
        ]
      }
    },
    {
      "action": "create",
      "resource": "reminder",
      "data": {
        "title": "Send offer to Tech Solutions",
        "due_at": "2026-02-10T09:00:00+00:00",
        "priority": "high",
        "remindable_type": "Project",
        "remindable_id": "$ref:new_project"
      }
    }
  ]
}
```

---

### Validate Operations

```
POST /api/v1/validate
```

Validate operations without executing them. Use this for pre-flight checks.

**Request Body:** Same as Batch Operations

**Response:**
```json
{
  "success": true,
  "data": {
    "valid": true,
    "total": 2,
    "validations": [
      {
        "index": 0,
        "valid": true,
        "errors": null,
        "warnings": null
      },
      {
        "index": 1,
        "valid": false,
        "errors": ["Client ID is required."],
        "warnings": null
      }
    ]
  }
}
```

**Validation Checks:**
- Resource type exists
- Action is valid for resource
- Required fields are present
- ID provided for update/delete operations
- Basic field type validation

**Note:** Validation does not check foreign key relationships or business rules that require database access.

---

## MCP Server Implementation Notes

### Tool Design Recommendations

Based on this API, an MCP server should implement these tools:

| Tool Name | API Endpoint | Description |
|-----------|--------------|-------------|
| `crm_list_clients` | `GET /clients` | Search and list clients |
| `crm_get_client` | `GET /clients/{id}` | Get client details |
| `crm_create_client` | `POST /clients` | Create new client |
| `crm_update_client` | `PUT /clients/{id}` | Update client |
| `crm_delete_client` | `DELETE /clients/{id}` | Delete client |
| `crm_list_projects` | `GET /projects` | Search and list projects |
| `crm_get_project` | `GET /projects/{id}` | Get project with items |
| `crm_create_project` | `POST /projects` | Create project with items |
| `crm_update_project` | `PUT /projects/{id}` | Update project |
| `crm_transition_project` | `POST /projects/{id}/transition` | Change project status |
| `crm_list_invoices` | `GET /invoices` | Search and list invoices |
| `crm_get_invoice` | `GET /invoices/{id}` | Get invoice details |
| `crm_create_invoice` | `POST /invoices` | Create invoice with items |
| `crm_create_invoice_from_project` | `POST /invoices/from-project` | Create invoice from project |
| `crm_mark_invoice_paid` | `POST /invoices/{id}/mark-paid` | Mark invoice as paid |
| `crm_list_reminders` | `GET /reminders` | List reminders (filterable) |
| `crm_create_reminder` | `POST /reminders` | Create reminder |
| `crm_complete_reminder` | `POST /reminders/{id}/complete` | Mark reminder complete |
| `crm_snooze_reminder` | `POST /reminders/{id}/snooze` | Snooze reminder |
| `crm_get_stats` | `GET /stats` | Get dashboard statistics |
| `crm_batch` | `POST /batch` | Execute multiple operations atomically |
| `crm_validate` | `POST /validate` | Validate operations before executing |

### Error Handling

All tools should:
1. Return structured error information including the error code
2. Include suggestions from the API response
3. Distinguish between validation errors (user fixable) and server errors

### Authentication

Store the API token in environment variable `CRM_API_TOKEN` and base URL in `CRM_API_URL`.

### Rate Limiting

Implement exponential backoff when receiving HTTP 429 responses. The rate limit is 60 requests per minute.

### Common Workflows

1. **Create Project from Scratch:**
   - Use `crm_list_clients` to find or verify client
   - Use `crm_create_project` with items
   - Use `crm_transition_project` to send offer

2. **Invoice a Completed Project:**
   - Use `crm_list_projects` with `status=completed`
   - Use `crm_create_invoice_from_project`

3. **Daily Summary:**
   - Use `crm_get_stats` for overview
   - Use `crm_list_reminders` with `status=pending` and `upcoming_days=7`

4. **Complex Operations:**
   - Use `crm_batch` for atomic multi-resource operations
   - Use `crm_validate` first to check for errors
