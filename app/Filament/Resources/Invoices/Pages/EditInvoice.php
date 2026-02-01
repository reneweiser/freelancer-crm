<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getDownloadPdfAction(),
            $this->getMarkSentAction(),
            $this->getMarkPaidAction(),
            ActionGroup::make([
                $this->getCancelAction(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])->icon('heroicon-o-ellipsis-vertical'),
        ];
    }

    protected function getDownloadPdfAction(): Action
    {
        return Action::make('downloadPdf')
            ->label('PDF herunterladen')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->url(fn (Invoice $record): string => route('pdf.invoice.download', $record))
            ->openUrlInNewTab();
    }

    protected function getMarkSentAction(): Action
    {
        return Action::make('markSent')
            ->label('Als gesendet markieren')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('Rechnung als gesendet markieren?')
            ->modalDescription('Die Rechnung wird als gesendet markiert.')
            ->action(function (Invoice $record): void {
                $record->update(['status' => InvoiceStatus::Sent]);
                Notification::make()
                    ->success()
                    ->title('Rechnung gesendet')
                    ->body("Rechnung {$record->number} wurde als gesendet markiert.")
                    ->send();
            })
            ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::Draft);
    }

    protected function getMarkPaidAction(): Action
    {
        return Action::make('markPaid')
            ->label('Als bezahlt markieren')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->form([
                DatePicker::make('paid_at')
                    ->label('Zahlungsdatum')
                    ->default(now())
                    ->required(),
                TextInput::make('payment_method')
                    ->label('Zahlungsart')
                    ->placeholder('z.B. Überweisung, PayPal'),
            ])
            ->action(function (Invoice $record, array $data): void {
                $record->markAsPaid($data);
                Notification::make()
                    ->success()
                    ->title('Zahlung erfasst')
                    ->body("Rechnung {$record->number} wurde als bezahlt markiert.")
                    ->send();
            })
            ->visible(fn (Invoice $record): bool => $record->status->isUnpaid());
    }

    protected function getCancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Stornieren')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Rechnung stornieren?')
            ->modalDescription('Die Rechnung wird storniert. Bezahlte Rechnungen können nicht storniert werden.')
            ->action(function (Invoice $record): void {
                $record->update(['status' => InvoiceStatus::Cancelled]);
                Notification::make()
                    ->warning()
                    ->title('Rechnung storniert')
                    ->body("Rechnung {$record->number} wurde storniert.")
                    ->send();
            })
            ->visible(fn (Invoice $record): bool => ! $record->status->isTerminal());
    }
}
