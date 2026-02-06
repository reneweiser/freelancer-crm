# Freelancer CRM

Open-source business management for freelance developers and IT consultants. Clients, projects, time tracking, invoicing, and follow-ups — self-hosted, private, yours.

**[See what's inside](https://reneweiser.github.io/freelancer-crm/)** — features, workflow, MCP integration, and deployment options at a glance.

## Quick Deploy

Pre-built images on GHCR. No local build step needed.

```bash
git clone https://github.com/reneweiser/freelancer-crm
cd freelancer-crm
cp .env.prod.example .env.prod
# Generate app key (no PHP required)
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env.prod
# Edit .env.prod — set APP_URL, DB_PASSWORD, DB_ROOT_PASSWORD, MAIL_* settings
nano .env.prod
# Pull and start
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d
```

On first startup, migrations run automatically and config is cached. Create your admin user:

```bash
docker compose -f docker-compose.prod.yml exec app php artisan make:filament-user
```

Open your browser. That's it.

### Portainer Stack

Prefer a GUI? Use `docker-compose.portainer.yml` — designed for Portainer's Stacks UI with Traefik reverse proxy. Set environment variables in the Portainer UI and deploy.

### Prerequisites

- Docker & Docker Compose
- A server with at least 1GB RAM

## MCP Server

Ships with a dedicated [MCP server](https://github.com/reneweiser/freelancer-crm-mcp) that connects Claude Code or Claude Desktop directly to your CRM data. Create projects from meeting notes, generate invoices from conversation, query your pipeline — all through natural language.

15 tools covering clients, projects, invoices, reminders, and stats. Batch operations, auto-pagination, Sanctum token auth.

## REST API

Full CRUD API with Sanctum authentication. Every resource is accessible programmatically — automate with cron jobs, scripts, or AI agents. Rate limited at 60 req/min.

## Tech Stack

Laravel 12 &middot; Filament 4 &middot; PHP 8.4+ &middot; Livewire 3 &middot; Tailwind CSS 4 &middot; MariaDB 11 &middot; Docker &middot; Sanctum &middot; DomPDF &middot; Pest 4

## Development Setup

```bash
composer install
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
```

### Commands

```bash
sail up -d                              # Start environment
sail down                               # Stop environment
sail composer run dev                   # Dev server with Vite
sail artisan migrate                    # Run migrations
sail artisan migrate:fresh --seed       # Reset with seeders
sail test                               # Run all tests
sail test --filter=TestName             # Run specific test
sail pint                               # Format code
sail pint --dirty                       # Format changed files only
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

### Project Structure

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

## Managing a Deployment

```bash
# View logs
docker compose -f docker-compose.prod.yml logs -f app

# Run artisan commands
docker compose -f docker-compose.prod.yml exec app php artisan <command>

# Update to latest version
docker compose -f docker-compose.prod.yml --env-file .env.prod pull
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d

# Stop services
docker compose -f docker-compose.prod.yml down
```

Data persists across container recreations via Docker volumes (`app-data` for uploads, `mariadb-data` for the database).

## Contributing

Contributions are welcome. Fork the repo, create a branch, and open a PR.

Before submitting, make sure tests pass and code is formatted:

```bash
sail test
sail pint --dirty
```

Architecture decisions and detailed specs live in `docs/planning/` — worth reading before bigger changes:

| Document | Description |
|----------|-------------|
| [02-data-model.md](docs/planning/02-data-model.md) | Database schema |
| [03-filament-resources.md](docs/planning/03-filament-resources.md) | Filament resource definitions |
| [04-workflows.md](docs/planning/04-workflows.md) | State machines and services |
| [07-technical-decisions.md](docs/planning/07-technical-decisions.md) | Architecture decision records |

## License

Open source under the [MIT License](LICENSE).
