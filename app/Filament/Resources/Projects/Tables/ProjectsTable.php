<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Titel')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('client.display_name')
                    ->label('Kunde')
                    ->searchable(['client.company_name', 'client.contact_name'])
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof ProjectType ? $state->getLabel() : $state),

                TextColumn::make('total_value')
                    ->label('Wert')
                    ->money('EUR'),

                TextColumn::make('offer_date')
                    ->label('Angebot')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Erstellt')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(ProjectStatus::class),

                SelectFilter::make('type')
                    ->label('Typ')
                    ->options(ProjectType::class),

                SelectFilter::make('client_id')
                    ->label('Kunde')
                    ->relationship('client', 'contact_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                    ->searchable()
                    ->preload(),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('downloadOffer')
                    ->label('Angebot PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn (Project $record): string => route('pdf.offer.download', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (Project $record): bool => in_array($record->status, [
                        ProjectStatus::Draft,
                        ProjectStatus::Sent,
                        ProjectStatus::Accepted,
                    ])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
