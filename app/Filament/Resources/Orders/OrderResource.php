<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Payments\PaymentWording;
use App\Payments\ProviderLinks;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $modelLabel = 'Order';

    protected static ?string $pluralModelLabel = 'Orders';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?int $navigationSort = 30;

    // Orders are placed by shoppers; the panel only reads them.
    public static function canCreate(): bool
    {
        return false;
    }

    // OrderPolicy is the storefront's "your own placed orders" rule; every admin may read any order here.
    public static function canView(Model $record): bool
    {
        return true;
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
            Section::make('Items')
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Item'),
                            TableColumn::make('Price'),
                            TableColumn::make('Quantity'),
                            TableColumn::make('Total'),
                        ])
                        ->schema([
                            TextEntry::make('product_name'),
                            TextEntry::make('unit_price')->state(fn (OrderItem $record) => $record->unit_price->format()),
                            TextEntry::make('quantity'),
                            TextEntry::make('line_total')->state(fn (OrderItem $record) => $record->line_total->format()),
                        ]),
                    Section::make()
                        ->columns(3)
                        ->schema([
                            TextEntry::make('subtotal')->state(fn (Order $record) => $record->subtotal->format()),
                            TextEntry::make('shipping_fee')->label('Delivery')->state(fn (Order $record) => $record->shipping_fee->format()),
                            TextEntry::make('total')->state(fn (Order $record) => $record->total->format())->weight('bold'),
                        ]),
                ]),
            Section::make('Customer')
                ->columns(2)
                ->schema([
                    TextEntry::make('user.name')
                        ->label('Name')
                        ->url(fn (Order $record) => CustomerResource::getUrl('view', ['record' => $record->user_id])),
                    TextEntry::make('user.email')->label('Email'),
                ]),
            Section::make('Payment')
                ->columns(2)
                ->schema([
                    TextEntry::make('status')->badge()->state(fn (Order $record) => Str::headline($record->status->value)),
                    TextEntry::make('paid_with')->label('Paid with')->state(fn (Order $record) => PaymentWording::tag($record)),
                    TextEntry::make('method_detail')
                        ->label('Method')
                        ->state(fn (Order $record) => PaymentWording::paidWith($record)),
                    TextEntry::make('wallet_amount')
                        ->label('Wallet part')
                        ->state(fn (Order $record) => $record->wallet_amount->format()),
                    TextEntry::make('provider_part')
                        ->label('Stripe or PayPal part')
                        ->state(fn (Order $record) => $record->total->subtract($record->wallet_amount)->format()),
                    TextEntry::make('reference')
                        ->label('Reference')
                        ->state(fn (Order $record) => self::reference($record))
                        ->placeholder('—'),
                    TextEntry::make('provider_link')
                        ->label("In the provider's dashboard")
                        ->state(fn (Order $record) => self::providerLink($record))
                        ->url(fn (Order $record) => self::providerLink($record), shouldOpenInNewTab: true)
                        ->placeholder('—'),
                ]),
            Section::make('Refund')
                ->columns(2)
                ->visible(fn (Order $record) => $record->refund !== null)
                ->schema([
                    TextEntry::make('refund.admin.name')->label('Refunded by')->placeholder('Deleted admin'),
                    TextEntry::make('refund_date')
                        ->label('Date')
                        ->state(fn (Order $record) => $record->refund?->completed_at ?: $record->refund?->created_at)
                        ->dateTime(),
                    TextEntry::make('refund.reason')->label('Reason')->columnSpanFull(),
                    TextEntry::make('refund_wallet')
                        ->label('Wallet amount')
                        ->state(fn (Order $record) => $record->refund?->wallet_amount->format()),
                    TextEntry::make('refund_provider')
                        ->label('Stripe or PayPal amount')
                        ->state(fn (Order $record) => $record->refund?->provider_amount->format()),
                    TextEntry::make('refund_status')
                        ->label('Status')
                        ->badge()
                        ->state(fn (Order $record) => Str::headline($record->refund->status->value ?? '')),
                    TextEntry::make('refund.provider_refund_id')->label('Provider refund ID')->placeholder('—'),
                    TextEntry::make('refund.failure_reason')
                        ->label('Failure reason')
                        ->visible(fn (Order $record) => $record->refund?->status === RefundStatus::Failed),
                ]),
            Section::make('Placed')
                ->schema([
                    TextEntry::make('created_at')->label('Date')->dateTime(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['user', 'succeededPayment'])
                ->withSum('items', 'quantity'))
            ->columns([
                TextColumn::make('id')->label('Order')->formatStateUsing(fn ($state) => "#{$state}")->searchable()->sortable(),
                TextColumn::make('created_at')->label('Date')->dateTime()->sortable(),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->description(fn (Order $record) => $record->user->email)
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('user', fn (Builder $user) => $user
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))),
                TextColumn::make('items_sum_quantity')->label('Items')->default(0)->sortable(),
                TextColumn::make('total')
                    ->state(fn (Order $record) => $record->total->format())
                    ->sortable(),
                TextColumn::make('paid_with')
                    ->label('Paid with')
                    ->state(fn (Order $record) => PaymentWording::tag($record)),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (Order $record) => Str::headline($record->status->value)),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $s) => [$s->value => Str::headline($s->value)])->all()),
                SelectFilter::make('payment')
                    ->label('Paid with')
                    ->options([
                        'wallet' => 'Wallet',
                        'stripe' => 'Stripe',
                        'paypal' => 'PayPal',
                        'wallet_stripe' => 'Wallet + Stripe',
                        'wallet_paypal' => 'Wallet + PayPal',
                    ])
                    ->query(fn (Builder $query, array $data) => self::filterByPayment($query, $data['value'] ?? null)),
                Filter::make('created_at')
                    ->label('Date')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->recordUrl(fn (Order $record) => static::getUrl('view', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }

    /**
     * The same split rule as Order::isSplit(): a hosted method with a wallet part.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    private static function filterByPayment(Builder $query, ?string $value): Builder
    {
        return match ($value) {
            'wallet' => $query->where('payment_method', PaymentMethod::Wallet),
            'stripe' => $query->where('payment_method', PaymentMethod::Stripe)->where('wallet_amount', 0),
            'paypal' => $query->where('payment_method', PaymentMethod::PayPal)->where('wallet_amount', 0),
            'wallet_stripe' => $query->where('payment_method', PaymentMethod::Stripe)->where('wallet_amount', '>', 0),
            'wallet_paypal' => $query->where('payment_method', PaymentMethod::PayPal)->where('wallet_amount', '>', 0),
            default => $query,
        };
    }

    private static function reference(Order $order): ?string
    {
        $payment = $order->succeededPayment;

        return $payment === null ? null : PaymentWording::reference($payment)['full'] ?? null;
    }

    private static function providerLink(Order $order): ?string
    {
        $payment = $order->succeededPayment;

        return $payment === null ? null : ProviderLinks::for($payment);
    }
}
