<?php

namespace App\Filament\Resources\Aisles;

use App\Filament\Resources\Aisles\Pages\CreateAisle;
use App\Filament\Resources\Aisles\Pages\EditAisle;
use App\Filament\Resources\Aisles\Pages\ListAisles;
use App\Models\Category;
use App\Rules\ProductPhoto;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AisleResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $modelLabel = 'Aisle';

    protected static ?string $pluralModelLabel = 'Aisles';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(120)
                ->unique(ignoreRecord: true)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, ?string $old, Get $get, Set $set, string $operation): void {
                    // Follow the name only while the slug is still the one the old name produced.
                    if ($operation === 'create' && ($get('slug') ?? '') === Str::slug($old ?? '')) {
                        $set('slug', Str::slug($state ?? ''));
                    }
                }),
            TextInput::make('slug')
                ->required()
                ->unique(ignoreRecord: true)
                ->regex('/^[a-z0-9-]+$/')
                ->validationMessages(['regex' => 'Use lowercase letters, digits and dashes only.']),
            FileUpload::make('image_path')
                ->label('Tile image')
                ->helperText('One square JPG or WebP photo, at least 1200 × 1200px and under 1 MB. Without one, the aisle shows its newest product photo.')
                ->disk('public')
                ->directory('aisles')
                ->image()
                ->rules([new ProductPhoto])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('')
                    ->disk('public')
                    ->square(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('products_count')->counts('products')->label('Products')->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                static::deleteAction(),
            ])
            ->recordUrl(fn (Category $record) => static::getUrl('edit', ['record' => $record]));
    }

    /**
     * Delete is refused while the aisle has products, counted at the moment of deletion.
     * The tile file goes with the aisle.
     */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->before(function (DeleteAction $action, Category $record): void {
                $count = $record->products()->count();

                if ($count === 0) {
                    return;
                }

                Notification::make()
                    ->danger()
                    ->title('Move its '.$count.' '.Str::plural('product', $count).' to another aisle first.')
                    ->send();

                $action->halt();
            })
            ->after(function (Category $record): void {
                if ($record->image_path !== null) {
                    Storage::disk('public')->delete($record->image_path);
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAisles::route('/'),
            'create' => CreateAisle::route('/create'),
            'edit' => EditAisle::route('/{record}/edit'),
        ];
    }
}
