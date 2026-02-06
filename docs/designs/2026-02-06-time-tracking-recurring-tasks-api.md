# Feature: Time Tracking & Recurring Tasks API

## Summary

Extends the REST API (`/api/v1/*`) with endpoints for `TimeEntry` and `RecurringTask` resources. Both models already existed with full business logic but had no API layer. This change adds RESTful CRUD endpoints, custom action endpoints (timer start/stop, task pause/resume/skip/advance), batch/validate integration, and stats dashboard sections.

## Requirements

### From Planning Documents

| Category | Selection | Details |
|----------|-----------|---------|
| Goal | Enhancement | Expose existing models via REST API |
| Users | Developers, AI Agents | MCP client and automation tools |
| Scope | Medium | 11 new files, 4 modified files |
| Testing | TDD | 45 new feature tests |

### Functional Requirements

1. **Time Entry CRUD** - Full CRUD with user scoping, invoiced-entry protection
2. **Timer Start/Stop** - Live timer with single-running-timer enforcement
3. **Recurring Task CRUD** - Full CRUD with global scope auto-filtering
4. **Task Actions** - Pause, resume, skip (with reason logging), advance
5. **Batch/Validate** - Integrated into existing AI helper endpoints
6. **Stats** - Time tracking hours and recurring task counts on dashboard

### Non-Functional Requirements

- Follows all existing API patterns (response format, error codes, pagination)
- User scoping: explicit `where('user_id', ...)` for TimeEntry (no global scope), global scope for RecurringTask
- Invoiced time entries are immutable (cannot update or delete)
- Only hourly projects accept time entries

## Architecture

### New Endpoints

#### Time Entries

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/time-entries` | List with filters: search, project_id, billable, invoiced, date_from, date_to |
| `POST` | `/api/v1/time-entries` | Create entry (project must be hourly and belong to user) |
| `GET` | `/api/v1/time-entries/{id}` | Show with project relation |
| `PUT` | `/api/v1/time-entries/{id}` | Update (blocked if invoiced) |
| `DELETE` | `/api/v1/time-entries/{id}` | Delete (blocked if invoiced) |
| `POST` | `/api/v1/time-entries/start` | Start live timer (enforces one running timer) |
| `POST` | `/api/v1/time-entries/{id}/stop` | Stop running timer (auto-calculates duration) |

#### Recurring Tasks

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/recurring-tasks` | List with filters: search, client_id, frequency, active, overdue |
| `POST` | `/api/v1/recurring-tasks` | Create task (validates client ownership if provided) |
| `GET` | `/api/v1/recurring-tasks/{id}` | Show with client + recent logs (limit 10) |
| `PUT` | `/api/v1/recurring-tasks/{id}` | Update |
| `DELETE` | `/api/v1/recurring-tasks/{id}` | Delete |
| `POST` | `/api/v1/recurring-tasks/{id}/pause` | Deactivate task |
| `POST` | `/api/v1/recurring-tasks/{id}/resume` | Reactivate task (adjusts next_due_at if in past) |
| `POST` | `/api/v1/recurring-tasks/{id}/skip` | Skip current occurrence with optional reason |
| `POST` | `/api/v1/recurring-tasks/{id}/advance` | Advance to next due date |

### Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `PROJECT_NOT_HOURLY` | 422 | Time entries require hourly project type |
| `TIME_ENTRY_INVOICED` | 422 | Cannot modify invoiced time entries |
| `TIMER_ALREADY_RUNNING` | 422 | User already has a running timer |
| `TIMER_NOT_RUNNING` | 422 | Time entry is not a running timer |
| `TASK_ALREADY_PAUSED` | 422 | Task is already inactive |
| `TASK_ALREADY_ACTIVE` | 422 | Task is already active |
| `TASK_NOT_ACTIVE` | 422 | Cannot skip/advance inactive task |

### Resource Schemas

#### TimeEntryResource

```json
{
  "id": 1,
  "project_id": 5,
  "invoice_id": null,
  "description": "Working on API endpoints",
  "started_at": "2026-02-06T09:00:00+00:00",
  "ended_at": "2026-02-06T11:30:00+00:00",
  "duration_minutes": 150,
  "duration_hours": 2.5,
  "formatted_duration": "2 Std. 30 Min.",
  "billable": true,
  "is_invoiced": false,
  "is_running": false,
  "created_at": "2026-02-06T09:00:00+00:00",
  "updated_at": "2026-02-06T11:30:00+00:00",
  "project": { /* ProjectResource */ },
  "invoice": null
}
```

#### RecurringTaskResource

```json
{
  "id": 1,
  "client_id": 3,
  "title": "Website-Wartung",
  "description": "Monthly maintenance tasks",
  "frequency": "monthly",
  "frequency_label": "Monatlich",
  "frequency_color": "primary",
  "next_due_at": "2026-03-01",
  "last_run_at": "2026-02-01",
  "started_at": "2025-01-01",
  "ends_at": "2027-01-01",
  "amount": 150.00,
  "formatted_amount": "150,00\u00a0\u20ac",
  "billing_notes": "Flat rate",
  "active": true,
  "is_overdue": false,
  "is_due_soon": false,
  "has_ended": false,
  "created_at": "2025-01-01T10:00:00+00:00",
  "updated_at": "2026-02-01T10:00:00+00:00",
  "client": { /* ClientResource */ },
  "logs": [
    {
      "id": 1,
      "due_date": "2026-02-01",
      "action": "reminder_created",
      "reminder_id": 42,
      "notes": null,
      "created_at": "2026-02-01T09:00:00+00:00"
    }
  ]
}
```

### Enumeration Values

#### TaskFrequency

| Value | Label (DE) | Color |
|-------|------------|-------|
| `weekly` | Wöchentlich | info |
| `monthly` | Monatlich | primary |
| `quarterly` | Vierteljährlich | warning |
| `yearly` | Jährlich | success |

### Stats Additions

The `GET /api/v1/stats` endpoint now includes two additional sections:

```json
{
  "time_tracking": {
    "total_hours_this_month": 120.5,
    "billable_hours_this_month": 98.25,
    "non_billable_hours_this_month": 22.25,
    "unbilled_amount": 8750.00
  },
  "recurring_tasks": {
    "total_active": 8,
    "total_inactive": 2,
    "overdue": 1,
    "due_soon": 3
  }
}
```

### Batch/Validate Integration

Both resources are integrated into the AI helper endpoints (`POST /api/v1/batch` and `POST /api/v1/validate`).

**Time Entry batch actions:** `create`, `update`, `delete`, `start`, `stop`

```json
{
  "action": "start",
  "resource": "time_entry",
  "data": { "project_id": 5, "description": "Feature work" }
}
```

**Recurring Task batch actions:** `create`, `update`, `delete`, `pause`, `resume`, `skip`, `advance`

```json
{
  "action": "skip",
  "resource": "recurring_task",
  "id": 3,
  "data": { "reason": "Client on vacation" }
}
```

Resource names accept both singular and plural forms (`time_entry`/`time_entries`, `recurring_task`/`recurring_tasks`).

## Implementation

### Files Created (11)

| File | Purpose |
|------|---------|
| `app/Http/Resources/Api/V1/TimeEntryResource.php` | API resource with computed fields |
| `app/Http/Resources/Api/V1/RecurringTaskResource.php` | API resource with enum labels/colors |
| `app/Http/Resources/Api/V1/RecurringTaskLogResource.php` | Nested log resource |
| `app/Http/Requests/Api/V1/StoreTimeEntryRequest.php` | Create validation |
| `app/Http/Requests/Api/V1/UpdateTimeEntryRequest.php` | Update validation (all `sometimes`) |
| `app/Http/Requests/Api/V1/StoreRecurringTaskRequest.php` | Create validation with `Rule::enum` |
| `app/Http/Requests/Api/V1/UpdateRecurringTaskRequest.php` | Update validation (all `sometimes`) |
| `app/Http/Controllers/Api/V1/TimeEntryController.php` | CRUD + start/stop controller |
| `app/Http/Controllers/Api/V1/RecurringTaskController.php` | CRUD + pause/resume/skip/advance controller |
| `tests/Feature/Api/V1/TimeEntryApiTest.php` | 21 tests covering all endpoints |
| `tests/Feature/Api/V1/RecurringTaskApiTest.php` | 24 tests covering all endpoints |

### Files Modified (4)

| File | Changes |
|------|---------|
| `routes/api.php` | Added route registrations for both resources + action routes |
| `app/Http/Controllers/Api/V1/StatsController.php` | Added `getTimeTrackingStats()` and `getRecurringTaskStats()` methods |
| `app/Http/Controllers/Api/V1/AiController.php` | Added handler/validator methods for both resources in batch/validate |
| `bootstrap/app.php` | Added new endpoints to ENDPOINT_NOT_FOUND suggestions |

### Key Design Decisions

1. **TimeEntry has no global scope** - Uses explicit `where('user_id', ...)` in controller queries and `withoutGlobalScope` is not needed. AiController also uses explicit user filtering.

2. **RecurringTask has global scope** - Controller queries auto-filter by authenticated user. AiController uses `withoutGlobalScope('user')` + explicit `where('user_id', ...)` to match the existing Reminder pattern.

3. **Timer enforcement** - Only one running timer allowed per user. A "running" timer is defined as: `started_at` set, `ended_at` null, `duration_minutes` null. Duration is auto-calculated via model's `saving` event when `ended_at` is set.

4. **Invoiced entry protection** - Time entries linked to an invoice (`invoice_id` not null) cannot be updated or deleted. This prevents data integrity issues with finalized invoices.

5. **Skip with logging** - The skip action delegates to `RecurringTaskService::skipOccurrence()` which creates a `RecurringTaskLog` entry with action `skipped` and optional reason, then advances the task.

## Testing

45 new feature tests organized in Pest `describe`/`it` blocks:

### TimeEntryApiTest (21 tests)

- **List (7):** pagination, filters (project_id, billable, invoiced, search, date range), includes project
- **Create (4):** valid data, non-hourly rejection, cross-user rejection, validation
- **Show (2):** computed fields, cross-user 404
- **Update (2):** valid update, invoiced rejection
- **Delete (2):** valid delete, invoiced rejection
- **Timer (4):** start, duplicate prevention, stop, already-stopped rejection

### RecurringTaskApiTest (24 tests)

- **List (8):** pagination, filters (search, client_id, frequency, active, overdue), includes client
- **Create (4):** valid data, without client, validation, client ownership
- **Show (2):** computed fields + logs, cross-user 404
- **Update (1):** valid update
- **Delete (1):** valid delete
- **Pause (2):** success, already-paused rejection
- **Resume (2):** success, already-active rejection
- **Skip (2):** success with reason + log verification, inactive rejection
- **Advance (2):** success, inactive rejection

### MCP Server Tool Additions

The following tools should be added to MCP server implementations:

| Tool Name | API Endpoint | Description |
|-----------|--------------|-------------|
| `crm_list_time_entries` | `GET /time-entries` | List/filter time entries |
| `crm_create_time_entry` | `POST /time-entries` | Create time entry |
| `crm_start_timer` | `POST /time-entries/start` | Start live timer |
| `crm_stop_timer` | `POST /time-entries/{id}/stop` | Stop running timer |
| `crm_list_recurring_tasks` | `GET /recurring-tasks` | List/filter recurring tasks |
| `crm_create_recurring_task` | `POST /recurring-tasks` | Create recurring task |
| `crm_pause_recurring_task` | `POST /recurring-tasks/{id}/pause` | Pause task |
| `crm_resume_recurring_task` | `POST /recurring-tasks/{id}/resume` | Resume task |
| `crm_skip_recurring_task` | `POST /recurring-tasks/{id}/skip` | Skip current occurrence |

### Common Workflows

1. **Track Time on Project:**
   - Use `crm_start_timer` with project_id to begin work
   - Use `crm_stop_timer` when done (duration auto-calculated)
   - Use `crm_list_time_entries` with `billable=true&invoiced=false` to see unbilled work

2. **Set Up Recurring Maintenance:**
   - Use `crm_create_recurring_task` with client_id, frequency, next_due_at
   - System auto-creates reminders when tasks come due
   - Use `crm_skip_recurring_task` with reason if client is unavailable

3. **Monthly Hours Summary:**
   - Use `crm_get_stats` to see `time_tracking.billable_hours_this_month` and `unbilled_amount`
   - Use `crm_list_time_entries` with `date_from` and `date_to` for detailed breakdown
