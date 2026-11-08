<?php

namespace App\Filament\Resources\Reviews;

use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Models\Review;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every customer review, to hide or delete. Admins don't write or edit
 * reviews. Hiding takes a review out of the product page and its stats
 * without touching updated_at, so it never shows as "Edited".
 */
class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $modelLabel = 'Review';

    protected static ?string $pluralModelLabel = 'Reviews';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 50;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product', 'user']))
            ->columns([
                TextColumn::make('product.name')->label('Product'),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('user', fn (Builder $user) => $user
                        ->where('name', 'like', "%{$search}%"))),
                TextColumn::make('stars')->sortable(),
                TextColumn::make('body')
                    ->label('Text')
                    ->limit(80)
                    ->wrap()
                    ->placeholder('Stars only')
                    ->searchable(),
                TextColumn::make('created_at')->label('Date')->dateTime()->sortable(),
                TextColumn::make('hidden')
                    ->label('')
                    ->badge()
                    ->color('warning')
                    ->state(fn (Review $record): ?string => $record->hidden_at === null ? null : 'Hidden'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('visibility')
                    ->label('Shown')
                    ->options(['visible' => 'Visible', 'hidden' => 'Hidden'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'visible' => $query->whereNull('hidden_at'),
                        'hidden' => $query->whereNotNull('hidden_at'),
                        default => $query,
                    }),
                SelectFilter::make('product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('hide')
                    ->label('Hide')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->visible(fn (Review $record): bool => $record->hidden_at === null)
                    ->action(function (Review $record): void {
                        self::setHiddenAt($record, now());
                        Notification::make()->success()->title('Review hidden')->send();
                    }),
                Action::make('unhide')
                    ->label('Unhide')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (Review $record): bool => $record->hidden_at !== null)
                    ->action(function (Review $record): void {
                        self::setHiddenAt($record, null);
                        Notification::make()->success()->title('Review shown again')->send();
                    }),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviews::route('/'),
        ];
    }

    /** Hides or shows a review without changing updated_at. */
    private static function setHiddenAt(Review $review, ?CarbonInterface $at): void
    {
        $review->timestamps = false;
        $review->forceFill(['hidden_at' => $at])->saveQuietly();
        $review->timestamps = true;
    }
}
