<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Models\Order;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Orders';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('id')->label('Order')->formatStateUsing(fn ($state) => "#{$state}"),
                TextColumn::make('created_at')->label('Placed')->dateTime(),
                TextColumn::make('total')->state(fn (Order $record) => $record->total->format()),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (Order $record) => Str::headline($record->status->value)),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No orders yet');
    }
}
