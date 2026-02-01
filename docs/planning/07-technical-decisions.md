# Technical Decisions & Architecture

## Technology Stack

| Layer | Technology | Version | Rationale |
|-------|------------|---------|-----------|
| Backend | Laravel | 12.11.* | Industry standard, excellent ecosystem |
| Admin Panel | Filament | 4.6 | Best Laravel admin panel, active development |
| PHP | PHP | 8.4 | Required by Filament 4.6, modern features |
| Database | MySQL | 8.0+ | Via Sail (dev) and Docker (prod) |
| Frontend | Livewire | 3.x | Included with Filament, reactive UI |
| CSS | Tailwind CSS | 4.x | Filament's styling framework |
| PDF | DomPDF | 2.x | Simple, works well for invoices |
| Testing | Pest PHP | 3.x | Modern, expressive testing |
| Dev Environment | Laravel Sail | Latest | Docker-based local development |
| Production | serversideup/php | 8.4-fpm-nginx | Production Docker image |
| AI Tooling | Laravel Boost | Latest | MCP for Laravel 12 & Filament 4 documentation |

---

## Development Environment: Laravel Sail

This project uses **Laravel Sail** for local development. All commands should be run through Sail.

### Command Reference

| Standard Command | Sail Equivalent |
|------------------|-----------------|
| `php artisan ...` | `sail artisan ...` |
| `composer ...` | `sail composer ...` |
| `npm ...` | `sail npm ...` |
| `phpunit` / `pest` | `sail test` |
| `./vendor/bin/pint` | `sail pint` |

### Common Sail Commands

```bash
# Start the development environment
sail up -d

# Stop the environment
sail down

# Run artisan commands
sail artisan migrate
sail artisan make:model Client -m
sail artisan make:filament-resource Client

# Install packages
sail composer require filament/filament:"^4.0" -W
sail composer require barryvdh/laravel-dompdf

# Run tests
sail test
sail test --filter=InvoiceTest

# Code formatting
sail pint

# NPM commands (for assets)
sail npm install
sail npm run build
sail npm run dev

# Database
sail artisan migrate:fresh --seed
sail artisan db:seed

# Tinker
sail tinker

# Help
sail --help
```

### Why Sail?

- Consistent environment across all developers
- No local PHP/MySQL installation required
- Matches production environment closely
- Easy to add services (Redis, Meilisearch, etc.)

---

## AI Tooling: Laravel Boost MCP

For accurate Laravel 12 and Filament 4 API information, we use **Laravel Boost** - an MCP that provides AI agents with searchable Laravel and Filament documentation.

### Installation

```bash
sail composer require laravel/boost --dev
sail artisan boost:install
# Select "Laravel" and "Filament" when prompted
```

### What Boost Provides

When installed, AI agents (Claude Code, Cursor, etc.) can:

- **Search Laravel documentation** for Eloquent, routing, validation, middleware, queues, and more
- **Search Filament documentation** for accurate, up-to-date patterns
- **Write idiomatic code** following Laravel and Filament conventions
- **Understand framework primitives** (Eloquent, Resources, Forms, Tables, Actions)
- **Follow structural patterns** for models, relationships, authorization

### Generated Files

After installation, Boost creates agent guideline files:
- `AGENTS.md` - General agent guidelines
- `CLAUDE.md` - Claude-specific guidelines (for Claude Code)
- Contains Laravel and Filament conventions and documentation search capabilities

### Why Boost Instead of Web Search?

- **Accuracy**: Direct access to official Laravel 12 and Filament 4 docs
- **Up-to-date**: Always reflects current API
- **Context-aware**: Understands your project structure
- **No hallucination**: Agents query actual docs instead of guessing

### Optional: Filament Blueprint (Premium)

For complex features, **Filament Blueprint** provides detailed implementation plans:

```bash
# Requires license from filamentphp.com
sail composer config repositories.filament composer https://packages.filamentphp.com/composer
sail composer require filament/blueprint --dev
sail artisan boost:install
```

Blueprint generates specifications including:
- Model attributes, casts, relationships, enums
- Resource scaffolding commands
- Form field components and validation
- Table columns, filters, actions
- Authorization policies
- Testing requirements

---

## Directory Structure

```
app/
├── Console/
│   └── Commands/
│       ├── CheckOverdueInvoices.php
│       ├── ProcessRecurringTasks.php
│       └── SendReminderNotifications.php
├── Enums/
│   ├── ClientType.php
│   ├── InvoiceStatus.php
│   ├── ProjectStatus.php
│   ├── ProjectType.php
│   └── ReminderFrequency.php
├── Filament/
│   ├── Pages/
│   │   ├── Dashboard.php
│   │   └── Settings.php
│   ├── Resources/
│   │   ├── ClientResource/
│   │   ├── InvoiceResource/
│   │   ├── ProjectResource/
│   │   ├── ReminderResource/
│   │   ├── RecurringTaskResource/
│   │   └── TimeEntryResource/
│   └── Widgets/
│       ├── StatsOverviewWidget.php
│       ├── UpcomingRemindersWidget.php
│       └── RecentActivityWidget.php
├── Models/
│   ├── Client.php
│   ├── Invoice.php
│   ├── InvoiceItem.php
│   ├── Project.php
│   ├── ProjectItem.php
│   ├── Reminder.php
│   ├── RecurringTask.php
│   ├── Setting.php
│   ├── TimeEntry.php
│   └── User.php
├── Policies/
│   ├── ClientPolicy.php
│   ├── InvoicePolicy.php
│   ├── ProjectPolicy.php
│   └── ...
├── Services/
│   ├── InvoiceCreationService.php
│   ├── InvoiceNumberService.php
│   ├── PdfService.php
│   ├── ReminderService.php
│   └── SettingsService.php
└── Traits/
    ├── HasSettings.php
    └── FormatsGermanCurrency.php

database/
├── factories/
├── migrations/
└── seeders/

resources/
├── views/
│   ├── filament/
│   │   └── pages/
│   │       └── settings.blade.php
│   └── pdfs/
│       ├── invoice.blade.php
│       └── offer.blade.php
└── lang/
    └── de/
        ├── filament.php
        └── validation.php

tests/
├── Feature/
│   ├── ClientTest.php
│   ├── InvoiceTest.php
│   ├── ProjectTest.php
│   └── WorkflowTest.php
└── Unit/
    ├── InvoiceCalculationTest.php
    └── InvoiceNumberTest.php
```

---

## Architectural Decisions

### ADR-001: Service Layer for Business Logic

**Context:** Complex operations like creating invoices from projects involve multiple models and calculations.

**Decision:** Extract business logic into service classes.

**Consequences:**
- Controllers/Resources remain thin
- Business logic is testable in isolation
- Reusable across different entry points (API, CLI, web)

```php
// Good: Service handles complexity
$invoice = app(InvoiceCreationService::class)->createFromProject($project);

// Bad: Fat controller
// $invoice = Invoice::create([...]);
// foreach ($project->items as $item) { ... }
// $invoice->calculateTotals();
```

### ADR-002: Enums for Status Fields

**Context:** Status fields need validation, display labels, and behavior logic.

**Decision:** Use PHP 8.1 enums for all status fields.

**Consequences:**
- Type safety for status values
- Centralized labels and colors
- Transition logic in one place
- Filament native enum support

```php
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    // ...

    public function label(): string { ... }
    public function color(): string { ... }
}
```

### ADR-003: Settings as Key-Value Store

**Context:** User settings (business name, tax number, etc.) need to be stored.

**Decision:** Use a key-value `settings` table instead of adding columns to users.

**Consequences:**
- Flexible: add new settings without migrations
- Can have default values in code
- Slightly more complex queries
- Need SettingsService for typed access

```php
// Access via service
$settings = app(SettingsService::class);
$businessName = $settings->get('business_name', 'My Business');

// Set value
$settings->set('business_name', 'Acme GmbH');
```

### ADR-004: Polymorphic Reminders

**Context:** Reminders can be attached to clients, projects, or invoices.

**Decision:** Use polymorphic `remindable` relationship.

**Consequences:**
- Single reminders table serves all entities
- Flexible attachment to any model
- Slightly more complex queries
- Need to handle type-specific logic

```php
// Reminder model
public function remindable(): MorphTo
{
    return $this->morphTo();
}

// On any model
public function reminders(): MorphMany
{
    return $this->morphMany(Reminder::class, 'remindable');
}
```

### ADR-005: Invoice Numbers with Year Prefix

**Context:** Invoice numbers need to be unique and follow German conventions.

**Decision:** Format: `YYYY-NNN` (e.g., `2026-001`), reset counter yearly.

**Consequences:**
- Compliant with German requirements
- Easy to identify invoice year
- Need to handle year transitions
- Concurrent creation needs locking

```php
class InvoiceNumberService
{
    public function generateNext(): string
    {
        return DB::transaction(function () {
            $year = now()->year;
            $lastNumber = Invoice::whereYear('issued_at', $year)
                ->lockForUpdate()
                ->max(DB::raw("CAST(SUBSTRING(number, 6) AS UNSIGNED)")) ?? 0;

            return sprintf('%d-%03d', $year, $lastNumber + 1);
        });
    }
}
```

### ADR-006: Soft Deletes for Financial Data

**Context:** Invoices and projects should not be permanently deleted for audit purposes.

**Decision:** Use soft deletes for clients, projects, and invoices.

**Consequences:**
- Data is never truly lost
- Can restore accidentally deleted records
- Need to filter deleted records in queries
- Storage grows over time

```php
// Models with soft deletes
class Invoice extends Model
{
    use SoftDeletes;
}

// Filter in resources
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()->withoutTrashed();
}
```

---

## Localization Strategy

### German UI

```php
// config/app.php
'locale' => 'de',
'timezone' => 'Europe/Berlin',

// Filament panel
->locale('de')
```

### Number Formatting

```php
// Helper for German number format
function formatCurrency(float $amount): string
{
    return Number::currency($amount, 'EUR', 'de_DE');
}

// Result: 1.234,56 €
```

### Date Formatting

```php
// German date format
$date->format('d.m.Y'); // 01.02.2026
$date->format('d.m.Y H:i'); // 01.02.2026 14:30
```

### Translation Files

```php
// resources/lang/de/filament.php
return [
    'pages' => [
        'dashboard' => [
            'title' => 'Dashboard',
        ],
    ],
    'resources' => [
        'client' => [
            'label' => 'Kunde',
            'plural_label' => 'Kunden',
        ],
        // ...
    ],
];
```

---

## Security Considerations

### Authentication

- Filament's built-in authentication
- Email verification recommended
- Password requirements: min 8 chars
- Future: 2FA support

### Authorization

```php
// Policy-based authorization
class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id
            && $invoice->status === InvoiceStatus::Draft;
    }
}
```

### Data Protection

- All routes require authentication
- CSRF protection enabled
- XSS prevention via Blade escaping
- SQL injection prevented via Eloquent
- Sensitive data not logged

### Tenant Isolation

```php
// Global scope for user data
class UserScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check()) {
            $builder->where('user_id', auth()->id());
        }
    }
}
```

---

## Testing Strategy

### Test Categories

| Type | Focus | Tools |
|------|-------|-------|
| Unit | Service logic, calculations | Pest |
| Feature | HTTP requests, forms | Pest + Livewire |
| Integration | Database, relationships | Pest + RefreshDatabase |

### Critical Test Cases

```php
// Invoice number generation
it('generates sequential invoice numbers', function () {
    $service = new InvoiceNumberService();

    expect($service->generateNext())->toBe('2026-001');
    Invoice::factory()->create(['number' => '2026-001']);
    expect($service->generateNext())->toBe('2026-002');
});

// Invoice calculation
it('calculates invoice totals correctly', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory()->state([
            'quantity' => 2,
            'unit_price' => 100,
            'vat_rate' => 19,
        ]))
        ->create();

    $invoice->calculateTotals();

    expect($invoice->subtotal)->toBe(200.00);
    expect($invoice->vat_amount)->toBe(38.00);
    expect($invoice->total)->toBe(238.00);
});

// Project workflow
it('prevents invalid status transitions', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Draft]);

    expect(fn () => $project->transitionTo(ProjectStatus::Completed))
        ->toThrow(InvalidStatusTransition::class);
});
```

---

## Production Deployment: Docker with serversideup/docker-php

Production deployment uses Docker Compose with [serversideup/docker-php](https://serversideup.net/open-source/docker-php/docs/getting-started) images.

### Why serversideup/docker-php?

- Production-optimized PHP images for Laravel
- Built-in Laravel automations (migrations, caching, etc.)
- Native health checks for container orchestration
- Multiple variants (FPM-NGINX, FPM-Apache, FrankenPHP)
- Security hardened for web deployment

### Deployment Strategy: Build Artifacts

We build immutable Docker images containing all code and assets. No volume mounts in production.

**Benefits:**
- Reproducible deployments
- No file permission issues
- Faster container startup
- Immutable infrastructure

### Dockerfile (in project root)

```dockerfile
# Dockerfile
FROM serversideup/php:8.4-fpm-nginx AS php_base

USER root
RUN install-php-extensions intl

WORKDIR /var/www/html

# Copy composer files only (for build cache)
COPY --chown=www-data:www-data composer.json composer.lock ./

# Install production PHP deps
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction


# -----------------------------------------------------
# NODE BUILD STAGE
# -----------------------------------------------------
FROM node:20 AS node_build

WORKDIR /app

# Copy only package files (for build cache)
COPY package.json package-lock.json ./

RUN npm ci

# Copy full source for build
COPY . .

RUN npm run build


# -----------------------------------------------------
# FINAL RUNTIME IMAGE
# No node, no node_modules, only PHP + built assets
# -----------------------------------------------------
FROM php_base AS final

WORKDIR /var/www/html

# Copy full Laravel code
COPY --chown=www-data:www-data . .

# Copy built assets from node stage
COPY --from=node_build /app/public/build ./public/build

# Fix permissions
RUN chmod -R 755 storage bootstrap/cache

USER www-data
```

### Docker Compose (Production)

```yaml
# docker-compose.prod.yml
services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    image: reneweiser-crm:latest
    environment:
      # Laravel Automations
      AUTORUN_ENABLED: "true"
      AUTORUN_LARAVEL_MIGRATION: "true"
      AUTORUN_LARAVEL_MIGRATION_TIMEOUT: "30"
      AUTORUN_LARAVEL_CONFIG_CACHE: "true"
      AUTORUN_LARAVEL_ROUTE_CACHE: "true"
      AUTORUN_LARAVEL_VIEW_CACHE: "true"
      AUTORUN_LARAVEL_STORAGE_LINK: "true"

      # Laravel Environment
      APP_NAME: "Freelancer CRM"
      APP_KEY: "${APP_KEY}"
      APP_ENV: production
      APP_DEBUG: "false"
      APP_URL: "${APP_URL}"
      APP_LOCALE: de
      APP_TIMEZONE: Europe/Berlin

      LOG_CHANNEL: stderr

      DB_CONNECTION: mysql
      DB_HOST: db
      DB_PORT: "3306"
      DB_DATABASE: crm
      DB_USERNAME: crm_user
      DB_PASSWORD: "${DB_PASSWORD}"

      SESSION_DRIVER: database
      CACHE_STORE: database
      QUEUE_CONNECTION: database

      MAIL_MAILER: smtp
      MAIL_HOST: "${MAIL_HOST}"
      MAIL_PORT: "587"
      MAIL_USERNAME: "${MAIL_USERNAME}"
      MAIL_PASSWORD: "${MAIL_PASSWORD}"
      MAIL_ENCRYPTION: tls
      MAIL_FROM_ADDRESS: "${MAIL_FROM_ADDRESS}"
      MAIL_FROM_NAME: "Freelancer CRM"
    depends_on:
      db:
        condition: service_healthy
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health"]
      interval: 30s
      timeout: 10s
      retries: 3
    ports:
      - "80:80"
      - "443:443"
    volumes:
      # Only persistent storage, not code
      - storage_app:/var/www/html/storage/app

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: "${DB_ROOT_PASSWORD}"
      MYSQL_DATABASE: crm
      MYSQL_USER: crm_user
      MYSQL_PASSWORD: "${DB_PASSWORD}"
    volumes:
      - db_data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5

  # Queue worker (uses same built image)
  queue:
    image: reneweiser-crm:latest
    command: php artisan queue:work --sleep=3 --tries=3 --max-jobs=1000
    environment:
      APP_NAME: "Freelancer CRM"
      APP_KEY: "${APP_KEY}"
      APP_ENV: production
      DB_CONNECTION: mysql
      DB_HOST: db
      DB_PORT: "3306"
      DB_DATABASE: crm
      DB_USERNAME: crm_user
      DB_PASSWORD: "${DB_PASSWORD}"
      QUEUE_CONNECTION: database
    depends_on:
      - app
      - db
    volumes:
      - storage_app:/var/www/html/storage/app

  # Scheduler (uses same built image)
  scheduler:
    image: reneweiser-crm:latest
    command: php artisan schedule:work
    environment:
      APP_NAME: "Freelancer CRM"
      APP_KEY: "${APP_KEY}"
      APP_ENV: production
      DB_CONNECTION: mysql
      DB_HOST: db
      DB_PORT: "3306"
      DB_DATABASE: crm
      DB_USERNAME: crm_user
      DB_PASSWORD: "${DB_PASSWORD}"
    depends_on:
      - app
      - db
    volumes:
      - storage_app:/var/www/html/storage/app

volumes:
  db_data:
  storage_app:
```

### Laravel Automation Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `AUTORUN_ENABLED` | Enable automation script | `false` |
| `AUTORUN_LARAVEL_MIGRATION` | Run migrations on start | `true` |
| `AUTORUN_LARAVEL_MIGRATION_TIMEOUT` | DB connection wait time | `30` |
| `AUTORUN_LARAVEL_CONFIG_CACHE` | Cache config | `false` |
| `AUTORUN_LARAVEL_ROUTE_CACHE` | Cache routes | `false` |
| `AUTORUN_LARAVEL_VIEW_CACHE` | Cache views | `false` |
| `AUTORUN_LARAVEL_STORAGE_LINK` | Create storage link | `false` |
| `AUTORUN_LARAVEL_OPTIMIZE` | Run full optimization | `false` |

### Deployment Workflow

```bash
# Build the production image
docker compose -f docker-compose.prod.yml build

# Or build and tag manually
docker build -t reneweiser-crm:latest .

# Deploy (pulls/builds image, starts containers)
docker compose -f docker-compose.prod.yml up -d

# Check logs
docker compose -f docker-compose.prod.yml logs -f app

# Run manual artisan commands
docker compose -f docker-compose.prod.yml exec app php artisan tinker

# Rebuild and redeploy after code changes
docker compose -f docker-compose.prod.yml build app
docker compose -f docker-compose.prod.yml up -d --no-deps app
```

### Environment File (.env.prod)

Store secrets separately from docker-compose.prod.yml:

```env
# .env.prod (not committed to git)
APP_KEY=base64:your_generated_key_here
APP_URL=https://crm.example.com

DB_PASSWORD=secure_random_password
DB_ROOT_PASSWORD=secure_random_root_password

MAIL_HOST=smtp.example.com
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_FROM_ADDRESS=rechnung@example.com
```

Load with: `docker compose -f docker-compose.prod.yml --env-file .env.prod up -d`

---

### Performance

- OPcache enabled in serversideup images by default
- Use Redis for session/cache if available
- Queue PDF generation for large batches
- Optimize database with proper indexes
- Use Laravel automations to cache config/routes/views

### Backup Strategy

- Daily database backups
- Weekly full backups
- Store backups off-site
- Test restore procedure regularly
- Consider MySQL dump via cron in scheduler container
