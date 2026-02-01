<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Enums\ClientType;
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

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label('Name')
                    ->searchable(['company_name', 'contact_name'])
                    ->sortable(['company_name']),

                TextColumn::make('type')
                    ->label('Typ')
                    ->badge(),

                TextColumn::make('email')
                    ->label('E-Mail')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('phone')
                    ->label('Telefon')
                    ->copyable(),

                TextColumn::make('city')
                    ->label('Ort')
                    ->searchable(),

                TextColumn::make('projects_count')
                    ->label('Projekte')
                    ->counts('projects'),

                TextColumn::make('invoices_sum_total')
                    ->label('Umsatz')
                    ->sum('invoices', 'total')
                    ->money('EUR'),

                TextColumn::make('created_at')
                    ->label('Erstellt')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Typ')
                    ->options(ClientType::class),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
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
