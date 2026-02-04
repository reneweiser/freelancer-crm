<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\UnorderedList;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
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

    public ?string $newTokenPlainText = null;

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTokenAlertComponent(),
                Grid::make(['lg' => 3])
                    ->schema([
                        Section::make('Aktive Tokens')
                            ->description('Verwalten Sie Ihre API-Tokens. Tokens ermöglichen den programmatischen Zugriff auf Ihre CRM-Daten.')
                            ->schema([
                                EmbeddedTable::make(),
                            ])
                            ->columnSpan(['lg' => 2]),
                        Grid::make(1)
                            ->schema([
                                $this->getSetupSection(),
                                $this->getSecuritySection(),
                            ])
                            ->columnSpan(['lg' => 1]),
                    ]),
            ]);
    }

    protected function getTokenAlertComponent(): Section
    {
        return Section::make('Neuer API-Token erstellt')
            ->description('Kopieren Sie diesen Token jetzt. Er wird aus Sicherheitsgründen nicht erneut angezeigt.')
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->iconColor('warning')
            ->schema([
                Text::make(fn () => $this->newTokenPlainText)
                    ->fontFamily(FontFamily::Mono)
                    ->size(TextSize::Small)
                    ->copyable()
                    ->weight(FontWeight::Medium),
                Actions::make([
                    Action::make('dismissToken')
                        ->label('Token wurde kopiert, Meldung ausblenden')
                        ->link()
                        ->color('warning')
                        ->action(fn () => $this->dismissToken()),
                ]),
            ])
            ->extraAttributes([
                'class' => 'bg-warning-50 dark:bg-warning-950 border-warning-300 dark:border-warning-700',
            ])
            ->visible(fn () => $this->newTokenPlainText !== null);
    }

    protected function getSetupSection(): Section
    {
        $baseUrl = config('app.url');

        return Section::make('Einrichtung')
            ->schema([
                Text::make('Claude Code MCP-Konfiguration')
                    ->weight(FontWeight::Bold)
                    ->color('neutral'),
                Text::make('Fügen Sie folgende Konfiguration zu Ihrer ~/.claude/claude_desktop_config.json hinzu:')
                    ->size(TextSize::Small)
                    ->color('neutral'),
                Text::make(<<<JSON
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
JSON)
                    ->fontFamily(FontFamily::Mono)
                    ->size(TextSize::ExtraSmall)
                    ->copyable(),
                Text::make('cURL-Beispiel')
                    ->weight(FontWeight::Bold)
                    ->color('neutral'),
                Text::make("curl -H \"Authorization: Bearer IHR_TOKEN_HIER\" {$baseUrl}/api/v1/clients")
                    ->fontFamily(FontFamily::Mono)
                    ->size(TextSize::ExtraSmall)
                    ->copyable(),
                Text::make('Verfügbare Endpunkte')
                    ->weight(FontWeight::Bold)
                    ->color('neutral'),
                UnorderedList::make([
                    Text::make('GET /api/v1/clients - Kunden auflisten')->size(TextSize::Small)->color('neutral'),
                    Text::make('GET /api/v1/projects - Projekte auflisten')->size(TextSize::Small)->color('neutral'),
                    Text::make('GET /api/v1/invoices - Rechnungen auflisten')->size(TextSize::Small)->color('neutral'),
                    Text::make('GET /api/v1/reminders - Erinnerungen auflisten')->size(TextSize::Small)->color('neutral'),
                    Text::make('GET /api/v1/stats - Statistiken abrufen')->size(TextSize::Small)->color('neutral'),
                    Text::make('POST /api/v1/batch - Batch-Operationen ausführen')->size(TextSize::Small)->color('neutral'),
                    Text::make('POST /api/v1/validate - Operationen validieren')->size(TextSize::Small)->color('neutral'),
                ])->columns(1),
            ]);
    }

    protected function getSecuritySection(): Section
    {
        return Section::make('Sicherheitshinweise')
            ->schema([
                $this->makeSecurityItem(
                    Heroicon::OutlinedShieldCheck,
                    'success',
                    'Tokens haben vollen Zugriff auf Ihre Daten. Teilen Sie sie nicht.'
                ),
                $this->makeSecurityItem(
                    Heroicon::OutlinedClock,
                    'info',
                    'Widerrufen Sie ungenutzte Tokens regelmäßig.'
                ),
                $this->makeSecurityItem(
                    Heroicon::OutlinedEyeSlash,
                    'warning',
                    'Speichern Sie Tokens sicher in Umgebungsvariablen.'
                ),
                $this->makeSecurityItem(
                    Heroicon::OutlinedArrowPath,
                    'gray',
                    'Rotieren Sie Tokens bei Verdacht auf Kompromittierung.'
                ),
            ]);
    }

    protected function makeSecurityItem(Heroicon $icon, string $color, string $text): Grid
    {
        return Grid::make(['default' => 12])
            ->schema([
                Icon::make($icon)
                    ->color($color)
                    ->columnSpan(1),
                Text::make($text)
                    ->size(TextSize::Small)
                    ->color('neutral')
                    ->columnSpan(11),
            ]);
    }

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
                ->schema([
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
                        ->success()
                        ->send();
                }),
        ];
    }

    public function dismissToken(): void
    {
        $this->newTokenPlainText = null;
    }
}
