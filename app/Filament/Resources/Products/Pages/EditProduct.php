<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\Pages\Concerns\SyncsProductPhotos;
use App\Filament\Resources\Products\ProductPhotos;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    use SyncsProductPhotos;

    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Product $product */
        $product = $this->getRecord();

        $data['price'] = number_format($product->price->cents() / 100, 2, '.', '');
        $data['photos'] = ProductPhotos::paths($product);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->takePhotosAndPrice($data);
    }

    protected function afterSave(): void
    {
        $this->syncPhotos();
    }
}
