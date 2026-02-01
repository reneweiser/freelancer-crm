# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Freelancer CRM - A personal CRM platform for freelance web developers and IT consultants. Built with Laravel 12.11.* and Filament 4.6 on PHP 8.4.

**Core workflow:** Client → Offer → Project → Invoice → Payment → Follow-up

**Locale:** German (EUR, dd.mm.yyyy format)

## Development Commands

All commands run through Laravel Sail:

```bash
sail up -d                              # Start environment
sail down                               # Stop environment
sail artisan migrate                    # Run migrations
sail artisan make:filament-resource X   # Create Filament resource
sail composer require package           # Install package
sail npm run build                      # Build frontend assets
sail test                               # Run all tests
sail test --filter=InvoiceTest          # Run specific test
sail pint                               # Code formatting
sail tinker                             # REPL
```

## Laravel & Filament Documentation

Use Laravel Boost MCP for accurate Laravel 12 and Filament 4.6 APIs:

```bash
sail composer require laravel/boost --dev
sail artisan boost:install  # Select "Laravel" and "Filament"
```

Query Boost for Laravel and Filament patterns instead of guessing or web searching. This ensures correct usage of Eloquent, routing, validation, middleware, queues, and all Filament components.

## Architecture

### Data Model

```
Users
  └── Clients (company/individual)
        ├── Projects (with ProjectItems)
        │     └── TimeEntries
        └── Invoices (with InvoiceItems)
  └── Reminders (polymorphic to Client/Project/Invoice)
  └── RecurringTasks
  └── Settings (key-value store)
```

### Key Patterns

- **Service Layer**: Business logic in `app/Services/` (e.g., `InvoiceCreationService`, `InvoiceNumberService`)
- **PHP Enums**: Status fields use enums with `label()` and `color()` methods for Filament integration
- **Polymorphic Reminders**: `remindable` morphTo relationship attaches to any entity
- **Soft Deletes**: Clients, Projects, Invoices use soft deletes for audit trail
- **User Scoping**: Global scope ensures users only see their own data

### Filament Structure

```
app/Filament/
├── Pages/           # Dashboard, Settings
├── Resources/       # ClientResource, ProjectResource, InvoiceResource, etc.
│   └── */Pages/     # List, Create, Edit pages per resource
│   └── */RelationManagers/
└── Widgets/         # StatsOverview, UpcomingReminders
```

### Status Workflows

**Project:** draft → sent → accepted/declined → in_progress → completed (or cancelled from any state)

**Invoice:** draft → sent → paid/overdue (or cancelled from non-paid states)

Invoice numbers: `YYYY-NNN` format (e.g., 2026-001), reset yearly with database locking for concurrent access.

## Production Deployment

Build immutable Docker images (no code volume mounts):

```bash
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d
```

Uses `serversideup/php:8.4-fpm-nginx` with Laravel automations for migrations and caching.

## Planning Documents

Detailed specs in `docs/planning/`:
- `02-data-model.md` - Full schema with migrations
- `03-filament-resources.md` - Resource definitions
- `04-workflows.md` - State machines and services
- `07-technical-decisions.md` - ADRs and deployment config
