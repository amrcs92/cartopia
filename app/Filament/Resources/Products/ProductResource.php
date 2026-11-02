<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Rules\ProductPhoto;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    public const int MAX_PHOTOS = 5;

    public const int MAX_PRICE_DOLLARS = 10000;

    protected static ?string $model = Product::class;

    protected static ?string $modelLabel = 'Product';

    protected static ?string $pluralModelLabel = 'Products';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    // Products are hidden from the shop rather than removed, so orders and baskets keep making sense.
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(120)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, ?string $old, Get $get, Set $set, string $operation): void {
                    // Follow the name only while the slug is still the one the old name produced.
                    if ($operation === 'create' && ($get('slug') ?? '') === Str::slug($old ?? '')) {
                        $set('slug', Str::slug($state ?? ''));
                    }
                }),
            TextInput::make('short_name')
                ->helperText('Shown on the home page price board.')
                ->maxLength(18),
            TextInput::make('slug')
                ->required()
                ->unique(ignoreRecord: true)
                ->regex('/^[a-z0-9-]+$/')
                ->validationMessages(['regex' => 'Use lowercase letters, digits and dashes only.']),
            TextInput::make('sku')
                ->label('SKU')
                ->required()
                ->unique(ignoreRecord: true),
            Select::make('category_id')
                ->label('Aisle')
                ->relationship('category', 'name')
                ->searchable()
                ->preload(),
            TextInput::make('price')
                ->label('Price (dollars)')
                ->placeholder('12.50')
                ->required()
                ->numeric()
                ->minValue(0.01)
                ->maxValue(self::MAX_PRICE_DOLLARS)
                ->rule('regex:/^\d+(\.\d{1,2})?$/'),
            Textarea::make('description')
                ->required()
                ->rows(4)
                ->columnSpanFull(),
            Toggle::make('is_featured')->label('Featured'),
            Toggle::make('is_visible')->label('Visible in shop')->default(true),
            FileUpload::make('photos')
                ->helperText('Up to 5 square JPG or WebP photos, at least 1200 × 1200px and under 1 MB. The first is the main photo.')
                ->multiple()
                ->reorderable()
                ->maxFiles(self::MAX_PHOTOS)
                ->disk('public')
                ->directory('products')
                ->image()
                ->rules([new ProductPhoto])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'primaryImage']))
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->disk('public')
                    ->state(fn (Product $record) => $record->primaryImage?->path)
                    ->square(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('sku')->label('SKU')->searchable(),
                TextColumn::make('category.name')->label('Aisle')->placeholder('—'),
                TextColumn::make('price')
                    ->state(fn (Product $record) => $record->price->format())
                    ->sortable(),
                IconColumn::make('is_featured')->label('Featured')->boolean(),
                IconColumn::make('is_visible')->label('Visible')->boolean(),
                TextColumn::make('updated_at')->label('Last changed')->dateTime()->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('category_id')->label('Aisle')->relationship('category', 'name'),
                TernaryFilter::make('is_featured')->label('Featured'),
                TernaryFilter::make('is_visible')->label('Visible')
                    ->trueLabel('Visible')
                    ->falseLabel('Hidden'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->recordUrl(fn (Product $record) => static::getUrl('edit', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
