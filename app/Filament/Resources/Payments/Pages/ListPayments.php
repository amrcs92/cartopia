<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'needs-attention' => Tab::make('Needs attention')
                ->badge(fn (): int => PaymentResource::needsAttention(PaymentResource::getEloquentQuery())->count())
                ->modifyQueryUsing(fn (Builder $query) => PaymentResource::needsAttention($query)),
        ];
    }
}
