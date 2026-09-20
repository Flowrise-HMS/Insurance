<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\Payer;

class PayersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('#')->rowIndex(),
                TextColumn::make('code')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('type')->searchable(),
                IconColumn::make('is_active'),
            ])
            ->filters([
                SelectFilter::make('type')->options(PayerType::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Payer $record): bool => ! $record->isSystem()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Per-record authorization runs PayerPolicy::delete(), which
                    // refuses the system NHIS payer; the model guard backs it up.
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }
}
