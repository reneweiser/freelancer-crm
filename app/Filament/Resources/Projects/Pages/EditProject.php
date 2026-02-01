<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\ProjectStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use App\Services\InvoiceCreationService;
use App\Services\SettingsService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getDownloadOfferPdfAction(),
            $this->getSendOfferAction(),
            $this->getAcceptOfferAction(),
            $this->getDeclineOfferAction(),
            $this->getStartProjectAction(),
            $this->getCompleteProjectAction(),
            $this->getReopenProjectAction(),
            $this->getCreateInvoiceAction(),
            ActionGroup::make([
                $this->getCancelAction(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])->icon('heroicon-o-ellipsis-vertical'),
        ];
    }

    protected function getDownloadOfferPdfAction(): Action
    {
        return Action::make('downloadOfferPdf')
            ->label('Angebot PDF')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->url(fn (Project $record): string => route('pdf.offer.download', $record))
            ->openUrlInNewTab()
            ->visible(fn (Project $record): bool => in_array($record->status, [
                ProjectStatus::Draft,
                ProjectStatus::Sent,
                ProjectStatus::Accepted,
            ]));
    }

    protected function getSendOfferAction(): Action
    {
        return Action::make('sendOffer')
            ->label('Als gesendet markieren')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('Angebot als gesendet markieren?')
            ->modalDescription('Das Angebot wird als gesendet markiert. Sie können das PDF unter "Aktionen" herunterladen.')
            ->action(function (Project $record): void {
                $record->sendOffer();
                Notification::make()
                    ->success()
                    ->title('Angebot gesendet')
                    ->body("Das Angebot wurde am {$record->offer_sent_at->format('d.m.Y')} als gesendet markiert.")
                    ->send();
            })
            ->visible(fn (Project $record): bool => $record->status === ProjectStatus::Draft);
    }

    protected function getAcceptOfferAction(): Action
    {
        return Action::make('acceptOffer')
            ->label('Angebot angenommen')
            ->icon('heroicon-o-check')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Angebot als angenommen markieren?')
            ->modalDescription('Der Kunde hat das Angebot akzeptiert.')
            ->action(function (Project $record): void {
                $record->acceptOffer();
                Notification::make()
                    ->success()
                    ->title('Angebot angenommen')
                    ->body('Das Projekt kann nun gestartet werden.')
                    ->send();
            })
            ->visible(fn (Project $record): bool => $record->status === ProjectStatus::Sent);
    }

    protected function getDeclineOfferAction(): Action
    {
        return Action::make('declineOffer')
            ->label('Angebot abgelehnt')
            ->icon('heroicon-o-x-mark')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Angebot als abgelehnt markieren?')
            ->modalDescription('Der Kunde hat das Angebot abgelehnt.')
            ->action(function (Project $record): void {
                $record->declineOffer();
                Notification::make()
                    ->warning()
                    ->title('Angebot abgelehnt')
                    ->body('Das Angebot wurde als abgelehnt markiert.')
                    ->send();
            })
            ->visible(fn (Project $record): bool => $record->status === ProjectStatus::Sent);
    }

    protected function getStartProjectAction(): Action
    {
        return Action::make('startProject')
            ->label('Projekt starten')
            ->icon('heroicon-o-play')
            ->color('warning')
            ->form([
                DatePicker::make('start_date')
                    ->label('Startdatum')
                    ->default(now())
                    ->required(),
            ])
            ->action(function (Project $record, array $data): void {
                $record->startProject($data['start_date']);
                Notification::make()
                    ->success()
                    ->title('Projekt gestartet')
                    ->body('Die Arbeit am Projekt kann beginnen.')
                    ->send();
            })
            ->visible(fn (Project $record): bool => $record->status === ProjectStatus::Accepted);
    }

    protected function getCompleteProjectAction(): Action
    {
        return Action::make('completeProject')
            ->label('Projekt abschließen')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->form([
                DatePicker::make('end_date')
                    ->label('Enddatum')
                    ->default(now())
                    ->required(),
            ])
            ->action(function (Project $record, array $data): void {
                $record->completeProject($data['end_date']);
                Notification::make()
                    ->success()
                    ->title('Projekt abgeschlossen')
                    ->body('Das Projekt wurde erfolgreich abgeschlossen.')
                    ->send();
            })
            ->visible(fn (Project $record): bool => $record->status === ProjectStatus::InProgress);
    }

    protected function getReopenProjectAction(): Action
    {
        return Action::make('reopenProject')
            ->label('Wieder öffnen')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Projekt wieder öffnen?')
            ->modalDescription('Das Projekt wird wieder in Bearbeitung gesetzt.')
            ->action(function (Project $record): void {
                $record->reopenProject();
                Notification::make()
                    ->success()
                    ->title('Projekt wieder geöffnet')
                    ->body('Das Projekt ist wieder in Bearbeitung.')
                    ->send();
            })
            ->visible(fn (Project $record): bool => $record->status === ProjectStatus::Completed);
    }

    protected function getCreateInvoiceAction(): Action
    {
        return Action::make('createInvoice')
            ->label('Rechnung erstellen')
            ->icon('heroicon-o-document-currency-euro')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Rechnung aus Projekt erstellen?')
            ->modalDescription('Eine neue Rechnung wird mit den Projektpositionen erstellt.')
            ->action(function (Project $record): void {
                $user = auth()->user();
                $settings = new SettingsService($user);
                $service = new InvoiceCreationService($settings);

                $invoice = $service->createFromProject($record);

                Notification::make()
                    ->success()
                    ->title('Rechnung erstellt')
                    ->body("Rechnung {$invoice->number} wurde erstellt.")
                    ->actions([
                        Action::make('view')
                            ->label('Anzeigen')
                            ->button()
                            ->url(InvoiceResource::getUrl('edit', ['record' => $invoice])),
                    ])
                    ->send();
            })
            ->visible(fn (Project $record): bool => $record->canBeInvoiced());
    }

    protected function getCancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Stornieren')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Projekt stornieren?')
            ->modalDescription('Das Projekt wird storniert und kann nicht mehr bearbeitet werden.')
            ->action(function (Project $record): void {
                $record->cancel();
                Notification::make()
                    ->warning()
                    ->title('Projekt storniert')
                    ->body('Das Projekt wurde storniert.')
                    ->send();
            })
            ->visible(fn (Project $record): bool => ! $record->status->isTerminal());
    }
}
