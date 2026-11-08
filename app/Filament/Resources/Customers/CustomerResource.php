<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\Customers\RelationManagers\WalletTransactionsRelationManager;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'Customer';

    protected static ?string $pluralModelLabel = 'Customers';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'name';

    // Customers sign themselves up; the panel only views them and adjusts wallets and admin access.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Details')
                ->columns(2)
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('email'),
                    TextEntry::make('phone')->placeholder('—'),
                    TextEntry::make('address')->placeholder('—'),
                    TextEntry::make('balance')
                        ->label('Wallet balance')
                        ->state(fn (User $record) => $record->balance->format()),
                    TextEntry::make('is_admin')
                        ->label('Role')
                        ->badge()
                        ->state(fn (User $record) => $record->is_admin ? 'Admin' : 'Shopper')
                        ->color(fn (string $state) => $state === 'Admin' ? 'primary' : 'gray'),
                    TextEntry::make('created_at')->label('Joined')->dateTime(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('orders'))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('balance')
                    ->state(fn (User $record) => $record->balance->format())
                    ->sortable(),
                TextColumn::make('orders_count')->label('Orders')->sortable(),
                TextColumn::make('created_at')->label('Joined')->date()->sortable(),
                TextColumn::make('is_admin')
                    ->label('Role')
                    ->badge()
                    ->state(fn (User $record) => $record->is_admin ? 'Admin' : null)
                    ->color('primary'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('admins')
                    ->label('Admins only')
                    ->query(fn (Builder $query) => $query->where('is_admin', true)),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->recordUrl(fn (User $record) => static::getUrl('view', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
            WalletTransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }
}
