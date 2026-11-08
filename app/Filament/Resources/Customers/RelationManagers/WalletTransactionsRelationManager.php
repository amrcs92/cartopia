<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Models\WalletTransaction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WalletTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'walletTransactions';

    protected static ?string $title = 'Wallet';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime(),
                TextColumn::make('type')->state(fn (WalletTransaction $record) => $record->type->label()),
                TextColumn::make('description')->wrap(),
                TextColumn::make('amount')
                    ->state(fn (WalletTransaction $record) => $record->amount->format())
                    ->color(fn (WalletTransaction $record) => $record->amount->isNegative() ? 'danger' : 'success'),
                TextColumn::make('balance_after')->label('Balance after')->state(fn (WalletTransaction $record) => $record->balance_after->format()),
            ])
            ->defaultSort(fn ($query) => $query->latest('created_at')->latest('id'))
            ->emptyStateHeading('No wallet activity yet');
    }
}
