# Token Management

## Overview

Filament UI for creating and managing API tokens (Sanctum personal access tokens) for AI agent authentication.

## User Flow

1. User navigates to Settings → API Tokens
2. Creates new token with a name (e.g., "Claude Code")
3. Token is displayed once (user must copy immediately)
4. User can view active tokens and revoke them

## Implementation

### Filament Page

```php
// app/Filament/Pages/ApiTokens.php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokens extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationLabel = 'API-Tokens';
    protected static ?string $navigationGroup = 'Einstellungen';
    protected static ?int $navigationSort = 100;

    protected static string $view = 'filament.pages.api-tokens';

    public ?string $newTokenName = null;
    public ?string $plainTextToken = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Neuen Token erstellen')
                ->icon('heroicon-o-plus')
                ->form([
                    Forms\Components\TextInput::make('name')
                        ->label('Token-Name')
                        ->placeholder('z.B. Claude Code, Automation')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $token = auth()->user()->createToken($data['name']);
                    $this->plainTextToken = $token->plainTextToken;

                    Notification::make()
                        ->title('Token erstellt')
                        ->body('Kopieren Sie den Token jetzt - er wird nicht erneut angezeigt.')
                        ->warning()
                        ->persistent()
                        ->send();
                }),
        ];
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(
                PersonalAccessToken::query()
                    ->where('tokenable_type', \App\Models\User::class)
                    ->where('tokenable_id', auth()->id())
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Erstellt am')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Zuletzt verwendet')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Nie')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('revoke')
                    ->label('Widerrufen')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Token widerrufen?')
                    ->modalDescription('Der Token kann danach nicht mehr verwendet werden.')
                    ->action(fn (PersonalAccessToken $record) => $record->delete()),
            ])
            ->emptyStateHeading('Keine API-Tokens')
            ->emptyStateDescription('Erstellen Sie einen Token für AI-Agenten oder Automatisierungen.')
            ->emptyStateIcon('heroicon-o-key');
    }
}
```

### Blade View

```blade
{{-- resources/views/filament/pages/api-tokens.blade.php --}}

<x-filament-panels::page>
    {{-- Show newly created token --}}
    @if($this->plainTextToken)
        <x-filament::section>
            <x-slot name="heading">
                Neuer API-Token erstellt
            </x-slot>
            <x-slot name="description">
                Kopieren Sie diesen Token jetzt. Er wird nur einmal angezeigt.
            </x-slot>

            <div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-4 font-mono text-sm break-all">
                {{ $this->plainTextToken }}
            </div>

            <div class="mt-4 flex gap-2">
                <x-filament::button
                    x-data="{}"
                    x-on:click="navigator.clipboard.writeText('{{ $this->plainTextToken }}').then(() => $tooltip('Kopiert!'))"
                    icon="heroicon-o-clipboard"
                >
                    Token kopieren
                </x-filament::button>

                <x-filament::button
                    color="gray"
                    wire:click="$set('plainTextToken', null)"
                >
                    Ausblenden
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

    {{-- Usage instructions --}}
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">
            Verwendung mit Claude Code
        </x-slot>

        <div class="prose dark:prose-invert max-w-none">
            <p>Fügen Sie diesen Server zu Ihrer Claude Code Konfiguration hinzu:</p>

            <pre><code class="language-json">{
  "reneweiser-crm": {
    "command": "php",
    "args": ["{{ base_path('artisan') }}", "mcp:serve"],
    "env": {
      "MCP_TOKEN": "IHR-TOKEN-HIER"
    }
  }
}</code></pre>

            <p>Speichern Sie diese Konfiguration in <code>~/.claude/mcp_servers.json</code></p>
        </div>
    </x-filament::section>

    {{-- Token list --}}
    {{ $this->table }}
</x-filament-panels::page>
```

## Security Considerations

### Token Hashing
Sanctum automatically hashes tokens before storage. Only the first 40 characters are stored (for lookup), the full hash is verified on authentication.

### Rate Limiting
Tokens inherit the API rate limit (60 req/min). Consider adding per-token limits in the future.

### Token Scopes (Future Enhancement)
```php
// Potential future enhancement for granular permissions
$token = $user->createToken('read-only', ['read']);
$token = $user->createToken('full-access', ['read', 'write', 'delete']);
```

## Files to Create

```
app/Filament/Pages/ApiTokens.php
resources/views/filament/pages/api-tokens.blade.php
```

## Migration (Sanctum)

Sanctum's `personal_access_tokens` table is created via:
```bash
sail artisan vendor:publish --tag=sanctum-migrations
sail artisan migrate
```

## Testing

```php
// tests/Feature/Api/AuthenticationTest.php

it('authenticates with valid token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/clients')
        ->assertOk();
});

it('rejects invalid token', function () {
    $this->withHeader('Authorization', 'Bearer invalid-token')
        ->getJson('/api/v1/clients')
        ->assertUnauthorized();
});

it('rejects missing token', function () {
    $this->getJson('/api/v1/clients')
        ->assertUnauthorized();
});
```
