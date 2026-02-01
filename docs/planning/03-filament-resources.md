# Filament 4 Resources & Panel Structure

## Panel Configuration

### Admin Panel (Primary)

Location: `app/Providers/Filament/AdminPanelProvider.php`

```php
return $panel
    ->default()
    ->id('admin')
    ->path('crm')
    ->login()
    ->colors([
        'primary' => Color::Blue,
    ])
    ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
    ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
    ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
    ->middleware([...])
    ->authMiddleware([Authenticate::class])
    ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
        return $builder
            ->groups([
                NavigationGroup::make('CRM')
                    ->items([
                        ...ClientResource::getNavigationItems(),
                        ...ProjectResource::getNavigationItems(),
                    ]),
                NavigationGroup::make('Finanzen')
                    ->items([
                        ...InvoiceResource::getNavigationItems(),
                        ...TimeEntryResource::getNavigationItems(),
                    ]),
                NavigationGroup::make('Organisation')
                    ->items([
                        ...ReminderResource::getNavigationItems(),
                        ...RecurringTaskResource::getNavigationItems(),
                    ]),
            ]);
    });
```

---

## Resource Structure (Filament 4 Pattern)

Following Filament 4's new directory structure:

```
app/Filament/Resources/
├── ClientResource/
│   ├── ClientResource.php
│   ├── Pages/
│   │   ├── CreateClient.php
│   │   ├── EditClient.php
│   │   └── ListClients.php
│   ├── RelationManagers/
│   │   ├── ProjectsRelationManager.php
│   │   └── InvoicesRelationManager.php
│   └── Widgets/
│       └── ClientStatsWidget.php
├── ProjectResource/
│   ├── ProjectResource.php
│   ├── Pages/
│   │   ├── CreateProject.php
│   │   ├── EditProject.php
│   │   ├── ListProjects.php
│   │   └── ViewProject.php
│   └── RelationManagers/
│       ├── ItemsRelationManager.php
│       └── TimeEntriesRelationManager.php
├── InvoiceResource/
│   ├── InvoiceResource.php
│   ├── Pages/
│   │   ├── CreateInvoice.php
│   │   ├── EditInvoice.php
│   │   └── ListInvoices.php
│   └── RelationManagers/
│       └── ItemsRelationManager.php
├── TimeEntryResource/
│   └── ...
├── ReminderResource/
│   └── ...
└── RecurringTaskResource/
    └── ...
```

---

## Resource Definitions

### ClientResource

```php
class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $modelLabel = 'Kunde';

    protected static ?string $pluralModelLabel = 'Kunden';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Kontaktdaten')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Typ')
                            ->options([
                                'company' => 'Unternehmen',
                                'individual' => 'Privatperson',
                            ])
                            ->default('company')
                            ->live(),

                        Forms\Components\TextInput::make('company_name')
                            ->label('Firmenname')
                            ->visible(fn (Get $get) => $get('type') === 'company')
                            ->required(fn (Get $get) => $get('type') === 'company')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_name')
                            ->label('Ansprechpartner')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('E-Mail')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->label('Telefon')
                            ->tel()
                            ->maxLength(50),

                        Forms\Components\TextInput::make('vat_id')
                            ->label('USt-IdNr.')
                            ->visible(fn (Get $get) => $get('type') === 'company')
                            ->maxLength(50),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Adresse')
                    ->schema([
                        Forms\Components\TextInput::make('street')
                            ->label('Straße')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('postal_code')
                            ->label('PLZ')
                            ->maxLength(10),

                        Forms\Components\TextInput::make('city')
                            ->label('Ort')
                            ->maxLength(255),

                        Forms\Components\Select::make('country')
                            ->label('Land')
                            ->options([
                                'DE' => 'Deutschland',
                                'AT' => 'Österreich',
                                'CH' => 'Schweiz',
                            ])
                            ->default('DE'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Notizen')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notizen')
                            ->rows(4),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Name')
                    ->searchable(['company_name', 'contact_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('E-Mail')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')
                    ->copyable(),

                Tables\Columns\TextColumn::make('projects_count')
                    ->label('Projekte')
                    ->counts('projects'),

                Tables\Columns\TextColumn::make('invoices_sum_total')
                    ->label('Umsatz')
                    ->sum('invoices', 'total')
                    ->money('EUR'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Erstellt')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Typ')
                    ->options([
                        'company' => 'Unternehmen',
                        'individual' => 'Privatperson',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    ExportBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ProjectsRelationManager::class,
            InvoicesRelationManager::class,
        ];
    }
}
```

### ProjectResource

```php
class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $modelLabel = 'Projekt';

    protected static ?string $pluralModelLabel = 'Projekte';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Projektdaten')
                    ->schema([
                        Forms\Components\Select::make('client_id')
                            ->label('Kunde')
                            ->relationship('client', 'company_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm(ClientResource::getFormSchema()),

                        Forms\Components\TextInput::make('title')
                            ->label('Titel')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label('Beschreibung')
                            ->rows(3),

                        Forms\Components\Select::make('type')
                            ->label('Abrechnungsart')
                            ->options([
                                'fixed' => 'Festpreis',
                                'hourly' => 'Nach Aufwand',
                            ])
                            ->default('fixed')
                            ->live(),

                        Forms\Components\TextInput::make('hourly_rate')
                            ->label('Stundensatz')
                            ->numeric()
                            ->prefix('€')
                            ->visible(fn (Get $get) => $get('type') === 'hourly'),

                        Forms\Components\TextInput::make('fixed_price')
                            ->label('Festpreis')
                            ->numeric()
                            ->prefix('€')
                            ->visible(fn (Get $get) => $get('type') === 'fixed'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(ProjectStatus::class)
                            ->default(ProjectStatus::Draft),

                        Forms\Components\DatePicker::make('offer_date')
                            ->label('Angebotsdatum')
                            ->default(now()),

                        Forms\Components\DatePicker::make('offer_valid_until')
                            ->label('Angebot gültig bis')
                            ->default(now()->addDays(30)),

                        Forms\Components\DatePicker::make('start_date')
                            ->label('Projektstart'),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('Projektende'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Positionen')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\TextInput::make('description')
                                    ->label('Beschreibung')
                                    ->required()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Menge')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0.01),

                                Forms\Components\Select::make('unit')
                                    ->label('Einheit')
                                    ->options([
                                        'Stück' => 'Stück',
                                        'Stunden' => 'Stunden',
                                        'Tage' => 'Tage',
                                        'Pauschal' => 'Pauschal',
                                    ])
                                    ->default('Stück'),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Einzelpreis')
                                    ->numeric()
                                    ->prefix('€')
                                    ->required(),
                            ])
                            ->columns(5)
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(1)
                            ->addActionLabel('Position hinzufügen'),
                    ]),

                Forms\Components\Section::make('Notizen')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Interne Notizen')
                            ->rows(3),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Titel')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.display_name')
                    ->label('Kunde')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Typ')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'fixed' => 'Festpreis',
                        'hourly' => 'Aufwand',
                    }),

                Tables\Columns\TextColumn::make('total_value')
                    ->label('Wert')
                    ->money('EUR'),

                Tables\Columns\TextColumn::make('offer_date')
                    ->label('Angebot')
                    ->date('d.m.Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(ProjectStatus::class),

                Tables\Filters\SelectFilter::make('client_id')
                    ->label('Kunde')
                    ->relationship('client', 'company_name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('sendOffer')
                        ->label('Angebot senden')
                        ->icon('heroicon-o-paper-airplane')
                        ->action(fn (Project $record) => $record->sendOffer())
                        ->visible(fn (Project $record) => $record->status === ProjectStatus::Draft),
                    Tables\Actions\Action::make('createInvoice')
                        ->label('Rechnung erstellen')
                        ->icon('heroicon-o-document-currency-euro')
                        ->action(fn (Project $record) => redirect(CreateInvoice::getUrl(['project' => $record->id])))
                        ->visible(fn (Project $record) => in_array($record->status, [ProjectStatus::Accepted, ProjectStatus::InProgress, ProjectStatus::Completed])),
                ]),
            ]);
    }
}
```

### InvoiceResource

```php
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-euro';

    protected static ?string $navigationGroup = 'Finanzen';

    protected static ?string $modelLabel = 'Rechnung';

    protected static ?string $pluralModelLabel = 'Rechnungen';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Rechnungsdaten')
                    ->schema([
                        Forms\Components\Select::make('client_id')
                            ->label('Kunde')
                            ->relationship('client', 'company_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('project_id')
                            ->label('Projekt (optional)')
                            ->relationship(
                                'project',
                                'title',
                                fn (Builder $query, Get $get) => $query->where('client_id', $get('client_id'))
                            )
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get) => filled($get('client_id'))),

                        Forms\Components\TextInput::make('number')
                            ->label('Rechnungsnummer')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn () => Invoice::generateNextNumber()),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(InvoiceStatus::class)
                            ->default(InvoiceStatus::Draft),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Datum')
                    ->schema([
                        Forms\Components\DatePicker::make('issued_at')
                            ->label('Rechnungsdatum')
                            ->required()
                            ->default(now()),

                        Forms\Components\DatePicker::make('due_at')
                            ->label('Fällig am')
                            ->required()
                            ->default(now()->addDays(14)),

                        Forms\Components\DatePicker::make('service_period_start')
                            ->label('Leistungszeitraum von'),

                        Forms\Components\DatePicker::make('service_period_end')
                            ->label('Leistungszeitraum bis'),

                        Forms\Components\DatePicker::make('paid_at')
                            ->label('Bezahlt am')
                            ->visible(fn (Get $get) => $get('status') === InvoiceStatus::Paid->value),

                        Forms\Components\TextInput::make('payment_method')
                            ->label('Zahlungsart')
                            ->visible(fn (Get $get) => $get('status') === InvoiceStatus::Paid->value),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Positionen')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\TextInput::make('description')
                                    ->label('Beschreibung')
                                    ->required()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Menge')
                                    ->numeric()
                                    ->default(1),

                                Forms\Components\Select::make('unit')
                                    ->label('Einheit')
                                    ->options([
                                        'Stück' => 'Stück',
                                        'Stunden' => 'Stunden',
                                        'Tage' => 'Tage',
                                        'Pauschal' => 'Pauschal',
                                    ]),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Einzelpreis')
                                    ->numeric()
                                    ->prefix('€')
                                    ->required(),

                                Forms\Components\Select::make('vat_rate')
                                    ->label('MwSt.')
                                    ->options([
                                        '19.00' => '19%',
                                        '7.00' => '7%',
                                        '0.00' => '0%',
                                    ])
                                    ->default('19.00'),
                            ])
                            ->columns(6)
                            ->reorderable()
                            ->defaultItems(1)
                            ->addActionLabel('Position hinzufügen')
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::calculateTotals($set, $get)),
                    ]),

                Forms\Components\Section::make('Summen')
                    ->schema([
                        Forms\Components\Placeholder::make('subtotal_display')
                            ->label('Netto')
                            ->content(fn (Get $get) => Number::currency($get('subtotal') ?? 0, 'EUR', 'de_DE')),

                        Forms\Components\Placeholder::make('vat_display')
                            ->label('MwSt.')
                            ->content(fn (Get $get) => Number::currency($get('vat_amount') ?? 0, 'EUR', 'de_DE')),

                        Forms\Components\Placeholder::make('total_display')
                            ->label('Brutto')
                            ->content(fn (Get $get) => Number::currency($get('total') ?? 0, 'EUR', 'de_DE')),

                        // Hidden fields for storage
                        Forms\Components\Hidden::make('subtotal'),
                        Forms\Components\Hidden::make('vat_amount'),
                        Forms\Components\Hidden::make('total'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Zusätzliche Angaben')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Bemerkungen')
                            ->rows(2),

                        Forms\Components\Textarea::make('footer_text')
                            ->label('Fußzeile')
                            ->rows(2)
                            ->default('Bitte überweisen Sie den Betrag innerhalb von 14 Tagen.'),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Nr.')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.display_name')
                    ->label('Kunde')
                    ->searchable(),

                Tables\Columns\TextColumn::make('issued_at')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_at')
                    ->label('Fällig')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Betrag')
                    ->money('EUR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->defaultSort('issued_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(InvoiceStatus::class),

                Tables\Filters\Filter::make('issued_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Von'),
                        Forms\Components\DatePicker::make('until')->label('Bis'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('issued_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('issued_at', '<=', $date));
                    }),

                Tables\Filters\SelectFilter::make('year')
                    ->label('Jahr')
                    ->options(fn () => Invoice::query()
                        ->selectRaw('YEAR(issued_at) as year')
                        ->distinct()
                        ->pluck('year', 'year'))
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn ($q, $year) => $q->whereYear('issued_at', $year))),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('download')
                        ->label('PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(fn (Invoice $record) => $record->downloadPdf()),
                    Tables\Actions\Action::make('send')
                        ->label('Versenden')
                        ->icon('heroicon-o-paper-airplane')
                        ->action(fn (Invoice $record) => $record->send())
                        ->visible(fn (Invoice $record) => $record->status === InvoiceStatus::Draft),
                    Tables\Actions\Action::make('markPaid')
                        ->label('Als bezahlt markieren')
                        ->icon('heroicon-o-check-circle')
                        ->form([
                            Forms\Components\DatePicker::make('paid_at')
                                ->label('Zahlungsdatum')
                                ->default(now())
                                ->required(),
                            Forms\Components\TextInput::make('payment_method')
                                ->label('Zahlungsart'),
                        ])
                        ->action(fn (Invoice $record, array $data) => $record->markAsPaid($data))
                        ->visible(fn (Invoice $record) => $record->status === InvoiceStatus::Sent),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(InvoiceExporter::class),
                ]),
            ]);
    }
}
```

---

## Dashboard Widgets

### StatsOverviewWidget

```php
class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $currentMonth = now()->startOfMonth();
        $currentYear = now()->startOfYear();

        return [
            Stat::make('Offene Rechnungen', Invoice::unpaid()->count())
                ->description(Invoice::unpaid()->sum('total') . ' €')
                ->descriptionIcon('heroicon-o-currency-euro')
                ->color('warning'),

            Stat::make('Umsatz (Monat)', Number::currency(
                Invoice::paid()->where('paid_at', '>=', $currentMonth)->sum('total'),
                'EUR', 'de_DE'
            ))
                ->description('Bezahlte Rechnungen')
                ->color('success'),

            Stat::make('Umsatz (Jahr)', Number::currency(
                Invoice::paid()->where('paid_at', '>=', $currentYear)->sum('total'),
                'EUR', 'de_DE'
            ))
                ->color('success'),

            Stat::make('Aktive Projekte', Project::active()->count())
                ->description('In Bearbeitung')
                ->color('primary'),
        ];
    }
}
```

### UpcomingRemindersWidget

```php
class UpcomingRemindersWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(Reminder::upcoming()->limit(10))
            ->columns([
                Tables\Columns\TextColumn::make('title'),
                Tables\Columns\TextColumn::make('due_at')
                    ->label('Fällig')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('remindable_type')
                    ->label('Bezug')
                    ->formatStateUsing(fn ($state) => class_basename($state)),
            ])
            ->actions([
                Tables\Actions\Action::make('complete')
                    ->icon('heroicon-o-check')
                    ->action(fn (Reminder $record) => $record->complete()),
            ]);
    }
}
```

---

## Export Configuration

### InvoiceExporter

```php
class InvoiceExporter extends Exporter
{
    protected static ?string $model = Invoice::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('number')
                ->label('Rechnungsnummer'),
            ExportColumn::make('client.company_name')
                ->label('Kunde'),
            ExportColumn::make('issued_at')
                ->label('Rechnungsdatum'),
            ExportColumn::make('due_at')
                ->label('Fällig am'),
            ExportColumn::make('paid_at')
                ->label('Bezahlt am'),
            ExportColumn::make('subtotal')
                ->label('Netto'),
            ExportColumn::make('vat_rate')
                ->label('MwSt.-Satz'),
            ExportColumn::make('vat_amount')
                ->label('MwSt.-Betrag'),
            ExportColumn::make('total')
                ->label('Brutto'),
            ExportColumn::make('status')
                ->label('Status'),
        ];
    }
}
```
