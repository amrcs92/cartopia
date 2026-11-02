<?php

namespace App\Filament\Resources\Products\Pages\Concerns;

use App\Filament\Resources\Products\ProductPhotos;
use App\Models\Product;
use App\Support\Money;

/**
 * Shared by the create and edit pages: price is typed in dollars and photos live in their own table.
 */
trait SyncsProductPhotos
{
    /** @var list<string> */
    protected array $photoPaths = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function takePhotosAndPrice(array $data): array
    {
        $this->photoPaths = array_values($data['photos'] ?? []);
        unset($data['photos']);

        $data['price'] = Money::fromDollars((string) $data['price']);

        return $data;
    }

    protected function syncPhotos(): void
    {
        /** @var Product $product */
        $product = $this->getRecord();

        ProductPhotos::sync($product, $this->photoPaths);
    }
}
