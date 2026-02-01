# Freelancer CRM

A personal CRM platform for freelance web developers and IT consultants. Manage clients, projects, invoices, and time tracking in one place.

## Tech Stack

- **Backend:** Laravel 12 + Filament 4.6
- **PHP:** 8.4+
- **Database:** MariaDB 11
- **Development:** Laravel Sail (Docker)
- **Testing:** Pest 4
- **Code Style:** Laravel Pint

## Core Features

**Workflow:** Client → Offer → Project → Invoice → Payment → Follow-up

- Project lifecycle management (offers, projects, invoices)
- Time tracking for hourly projects
- Invoice generation with sequential numbering (YYYY-NNN format)
- Client relationship tracking with reminders
- Tax-ready financial exports

**Locale:** German (EUR, dd.mm.yyyy format)

## Development Setup

### Prerequisites

- Docker & Docker Compose
- PHP 8.4+ (for Composer outside container)

### Installation

```bash
# Clone repository
git clone <repository-url>
cd reneweiser-crm

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Start Sail environment
./vendor/bin/sail up -d

# Generate app key
./vendor/bin/sail artisan key:generate

# Run migrations
./vendor/bin/sail artisan migrate

# Build frontend assets
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

### Quick Start (after setup)

```bash
./vendor/bin/sail up -d          # Start environment
./vendor/bin/sail artisan tinker # REPL
./vendor/bin/sail down           # Stop environment
```

## Development Commands

```bash
# Environment
./vendor/bin/sail up -d                              # Start containers
./vendor/bin/sail down                               # Stop containers
./vendor/bin/sail composer run dev                   # Start dev server with Vite

# Database
./vendor/bin/sail artisan migrate                    # Run migrations
./vendor/bin/sail artisan migrate:fresh --seed       # Reset with seeders

# Filament
./vendor/bin/sail artisan make:filament-resource X   # Create resource
./vendor/bin/sail artisan make:filament-page X       # Create page

# Testing
./vendor/bin/sail artisan test                       # Run all tests
./vendor/bin/sail artisan test --filter=TestName     # Run specific test

# Code Quality
./vendor/bin/sail bin pint                           # Format code
./vendor/bin/sail bin pint --dirty                   # Format changed files only
```

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

- **Service Layer:** Business logic in `app/Services/`
- **PHP Enums:** Status fields with `label()` and `color()` methods for Filament
- **Polymorphic Reminders:** Attach reminders to any entity
- **Soft Deletes:** Clients, Projects, Invoices for audit trail
- **User Scoping:** Global scope ensures data isolation

### Status Workflows

**Project:** draft → sent → accepted/declined → in_progress → completed

**Invoice:** draft → sent → paid/overdue (or cancelled)

## Project Structure

```
app/
├── Filament/
│   ├── Pages/              # Dashboard, Settings
│   ├── Resources/          # ClientResource, ProjectResource, etc.
│   └── Widgets/            # StatsOverview, UpcomingReminders
├── Models/                 # Eloquent models
├── Services/               # Business logic
└── Enums/                  # Status enums
```

## Documentation

Detailed specifications in `docs/planning/`:

| Document | Description |
|----------|-------------|
| [00-overview.md](docs/planning/00-overview.md) | Project summary |
| [01-requirements.md](docs/planning/01-requirements.md) | User stories |
| [02-data-model.md](docs/planning/02-data-model.md) | Database schema |
| [03-filament-resources.md](docs/planning/03-filament-resources.md) | Filament resources |
| [04-workflows.md](docs/planning/04-workflows.md) | State machines |
| [05-features-mvp.md](docs/planning/05-features-mvp.md) | MVP scope |
| [07-technical-decisions.md](docs/planning/07-technical-decisions.md) | ADRs |

## Production Deployment

Uses immutable Docker images with `serversideup/php:8.4-fpm-nginx`:

```bash
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d
```

## License

Proprietary - All rights reserved.
