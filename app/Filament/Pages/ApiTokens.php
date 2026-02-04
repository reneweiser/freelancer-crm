<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokens extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'API-Tokens';

    protected static ?string $title = 'API-Tokens';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.api-tokens';

    public ?string $newTokenPlainText = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PersonalAccessToken::query()
                    ->where('tokenable_type', \App\Models\User::class)
                    ->where('tokenable_id', Auth::id())
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_used_at')
                    ->label('Zuletzt verwendet')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Nie verwendet')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Erstellt am')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Widerrufen')
                    ->modalHeading('Token widerrufen')
                    ->modalDescription('Sind Sie sicher, dass Sie diesen Token widerrufen möchten? Diese Aktion kann nicht rückgängig gemacht werden. Anwendungen, die diesen Token verwenden, verlieren den Zugriff.')
                    ->modalSubmitActionLabel('Widerrufen')
                    ->successNotificationTitle('Token widerrufen'),
            ])
            ->emptyStateHeading('Keine API-Tokens')
            ->emptyStateDescription('Erstellen Sie einen Token, um auf die API zuzugreifen.')
            ->emptyStateIcon('heroicon-o-key');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createToken')
                ->label('Neuen Token erstellen')
                ->icon('heroicon-o-plus')
                ->form([
                    TextInput::make('name')
                        ->label('Token-Name')
                        ->placeholder('z.B. Claude Code')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Ein beschreibender Name zur Identifizierung des Tokens.'),
                ])
                ->action(function (array $data): void {
                    $token = Auth::user()->createToken($data['name']);
                    $this->newTokenPlainText = $token->plainTextToken;

                    Notification::make()
                        ->title('Token erstellt')
                        ->body('Kopieren Sie den Token jetzt - er wird nur einmal angezeigt!')
                        ->warning()
                        ->persistent()
                        ->send();
                }),
        ];
    }

    public function dismissToken(): void
    {
        $this->newTokenPlainText = null;
    }

    public function getSetupInstructions(): string
    {
        $baseUrl = config('app.url');

        return <<<INSTRUCTIONS
        ## Claude Code MCP-Konfiguration

        Fügen Sie folgende Konfiguration zu Ihrer `~/.claude/claude_desktop_config.json` hinzu:

        ```json
        {
          "mcpServers": {
            "freelancer-crm": {
              "command": "npx",
              "args": ["-y", "@anthropic/mcp-proxy", "{$baseUrl}/api/v1"],
              "env": {
                "API_TOKEN": "IHR_TOKEN_HIER"
              }
            }
          }
        }
        ```

        ## cURL-Beispiel

        ```bash
        curl -H "Authorization: Bearer IHR_TOKEN_HIER" \\
             {$baseUrl}/api/v1/clients
        ```

        ## Verfügbare Endpunkte

        - `GET /api/v1/clients` - Kunden auflisten
        - `GET /api/v1/projects` - Projekte auflisten
        - `GET /api/v1/invoices` - Rechnungen auflisten
        - `GET /api/v1/reminders` - Erinnerungen auflisten
        - `GET /api/v1/stats` - Statistiken abrufen
        - `POST /api/v1/batch` - Batch-Operationen ausführen
        - `POST /api/v1/validate` - Operationen validieren
        INSTRUCTIONS;
    }
}
