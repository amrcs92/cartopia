<?php

namespace App\Filament\Resources\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Models\Payment;
use App\Payments\PaymentWording;
use App\Payments\ProviderLinks;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PaymentResource extends Resource
{
    /** A pending payment older than this needs someone to look at it. */
    public const int STALE_AFTER_MINUTES = 30;

    protected static ?string $model = Payment::class;

    protected static ?string $modelLabel = 'Payment';

    protected static ?string $pluralModelLabel = 'Payments';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 35;

    // Payments are made by shoppers and settled by the providers; the panel only reads them.
    public static function canCreate(): bool
    {
        return false;
    }

    // PaymentPolicy is the storefront's "your own payments" rule; every admin may read any payment here.
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

    /**
     * Stripe and PayPal payments only; wallet payments have nothing to check with a provider.
     *
     * @return Builder<Payment>
     */
    public static function getEloquentQuery(): Builder
    {
        return Payment::query()->whereIn('method', PaymentMethod::hosted());
    }

    /**
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public static function needsAttention(Builder $query): Builder
    {
        return $query
            ->where('status', PaymentStatus::Pending)
            ->where('created_at', '<', now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payment')
                ->columns(2)
                ->schema([
                    TextEntry::make('method')->state(fn (Payment $record) => $record->method->label()),
                    TextEntry::make('status')->badge()->state(fn (Payment $record) => Str::headline($record->status->value)),
                    TextEntry::make('amount')->state(fn (Payment $record) => $record->amount->format()),
                    TextEntry::make('purpose')->state(fn (Payment $record) => self::purpose($record)),
                    TextEntry::make('card')
                        ->label('Card')
                        ->state(fn (Payment $record) => PaymentWording::cardBrand($record->card_brand)." ending in {$record->card_last4}")
                        ->visible(fn (Payment $record) => $record->card_last4 !== null),
                    TextEntry::make('reference')
                        ->label('Reference')
                        ->state(fn (Payment $record) => PaymentWording::reference($record)['full'] ?? null)
                        ->placeholder('—'),
                    TextEntry::make('provider_payment_id')->label('Provider payment id')->placeholder('—'),
                    TextEntry::make('provider_link')
                        ->label("In the provider's dashboard")
                        ->state(fn (Payment $record) => ProviderLinks::for($record))
                        ->url(fn (Payment $record) => ProviderLinks::for($record), shouldOpenInNewTab: true)
                        ->visible(fn (Payment $record) => ProviderLinks::for($record) !== null),
                ]),
            Section::make('Customer')
                ->columns(2)
                ->schema([
                    TextEntry::make('user.name')
                        ->label('Name')
                        ->url(fn (Payment $record) => CustomerResource::getUrl('view', ['record' => $record->user_id])),
                    TextEntry::make('user.email')->label('Email'),
                    TextEntry::make('order_link')
                        ->label('Order')
                        ->state(fn (Payment $record) => "Order #{$record->order_id}")
                        ->url(fn (Payment $record) => OrderResource::getUrl('view', ['record' => $record->order_id]))
                        ->visible(fn (Payment $record) => $record->order_id !== null),
                ]),
            Section::make('Dates')
                ->columns(2)
                ->schema([
                    TextEntry::make('created_at')->label('Started')->dateTime(),
                    TextEntry::make('completed_at')->label('Completed')->dateTime()->placeholder('—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user']))
            ->columns([
                TextColumn::make('id')->label('ID')->searchable()->sortable(),
                TextColumn::make('created_at')->label('Date')->dateTime()->sortable(),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->description(fn (Payment $record) => $record->user->email)
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('user', fn (Builder $user) => $user
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))),
                TextColumn::make('purpose')->state(fn (Payment $record) => self::purpose($record)),
                TextColumn::make('method')->state(fn (Payment $record) => $record->method->label()),
                TextColumn::make('amount')->state(fn (Payment $record) => $record->amount->format())->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (Payment $record) => Str::headline($record->status->value)),
                TextColumn::make('reference')
                    ->label('Reference')
                    ->state(fn (Payment $record) => PaymentWording::reference($record)['full'] ?? null)
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(fn (PaymentStatus $s) => [$s->value => Str::headline($s->value)])->all()),
                SelectFilter::make('method')
                    ->options(collect(PaymentMethod::hosted())->mapWithKeys(fn (PaymentMethod $m) => [$m->value => $m->label()])->all()),
                SelectFilter::make('purpose')
                    ->options([
                        PaymentPurpose::Order->value => 'Order',
                        PaymentPurpose::TopUp->value => 'Top-up',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->recordUrl(fn (Payment $record) => static::getUrl('view', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }

    private static function purpose(Payment $payment): string
    {
        return $payment->purpose === PaymentPurpose::Order ? "Order #{$payment->order_id}" : 'Top-up';
    }
}
