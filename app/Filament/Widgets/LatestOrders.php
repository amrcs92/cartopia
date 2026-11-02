<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Str;

class LatestOrders extends TableWidget
{
    protected static ?string $heading = 'Latest orders';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->with('user')->latest()->latest('id')->limit(5))
            ->columns([
                TextColumn::make('id')->label('Order')->formatStateUsing(fn ($state) => "#{$state}"),
                TextColumn::make('user.name')->label('Customer'),
                TextColumn::make('total')->state(fn (Order $record) => $record->total->format()),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (Order $record) => Str::headline($record->status->value)),
            ])
            ->paginated(false)
            ->recordUrl(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]));
    }
}
