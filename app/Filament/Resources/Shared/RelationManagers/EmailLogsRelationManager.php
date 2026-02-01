<?php

namespace App\Filament\Resources\Shared\RelationManagers;

use App\Enums\EmailLogStatus;
use App\Enums\EmailLogType;
use App\Jobs\SendInvoiceEmail;
use App\Jobs\SendOfferEmail;
use App\Jobs\SendPaymentReminderEmail;
use App\Models\EmailLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmailLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'emailLogs';

    protected static ?string $title = 'Gesendete E-Mails';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (EmailLogType $state): string => $state->getLabel()),

                TextColumn::make('recipient_email')
                    ->label('Empfänger')
                    ->searchable(),

                TextColumn::make('subject')
                    ->label('Betreff')
                    ->limit(40)
                    ->tooltip(fn (EmailLog $record): string => $record->subject),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (EmailLogStatus $state): string => $state->getLabel())
                    ->color(fn (EmailLogStatus $state): string => $state->getColor()),

                TextColumn::make('sent_at')
                    ->label('Gesendet')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Ausstehend')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Erstellt')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('retry')
                    ->label('Erneut senden')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (EmailLog $record): bool => $record->status === EmailLogStatus::Failed)
                    ->requiresConfirmation()
                    ->modalHeading('E-Mail erneut senden?')
                    ->modalDescription('Die E-Mail wird erneut in die Warteschlange gestellt.')
                    ->action(function (EmailLog $record): void {
                        $record->resetForRetry();

                        $this->dispatchEmailJob($record);

                        Notification::make()
                            ->title('E-Mail wird erneut gesendet')
                            ->success()
                            ->send();
                    }),

                Action::make('viewError')
                    ->label('Fehler anzeigen')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn (EmailLog $record): bool => $record->status === EmailLogStatus::Failed && ! empty($record->error_message))
                    ->modalHeading('Fehlermeldung')
                    ->modalContent(fn (EmailLog $record) => view('filament.modals.email-error', ['log' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Schließen'),
            ])
            ->emptyStateHeading('Keine E-Mails gesendet')
            ->emptyStateDescription('Hier werden alle gesendeten E-Mails für diesen Eintrag angezeigt.');
    }

    protected function dispatchEmailJob(EmailLog $emailLog): void
    {
        $emailable = $emailLog->emailable;

        match ($emailLog->type) {
            EmailLogType::Offer => SendOfferEmail::dispatch($emailable, $emailLog),
            EmailLogType::Invoice => SendInvoiceEmail::dispatch($emailable, $emailLog),
            EmailLogType::PaymentReminder => SendPaymentReminderEmail::dispatch($emailable, $emailLog),
            default => null,
        };
    }
}
