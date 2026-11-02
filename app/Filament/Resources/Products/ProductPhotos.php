<?php

namespace App\Filament\Resources\Products;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;

/**
 * Keeps a product's `product_images` rows and files in step with the form's ordered list of photo paths.
 */
class ProductPhotos
{
    /**
     * Paths for the form, in order. Rows whose file is gone are left out, as the upload field drops them too.
     *
     * @return list<string>
     */
    public static function paths(Product $product): array
    {
        $paths = [];

        foreach ($product->images as $image) {
            if (Storage::disk('public')->exists($image->path)) {
                $paths[] = $image->path;
            }
        }

        return $paths;
    }

    /**
     * @param  array<array-key, string>  $paths  the form's photos, first is the main photo
     */
    public static function sync(Product $product, array $paths): void
    {
        $paths = array_values($paths);
        $existing = $product->images()->get()->keyBy('path');

        // A row whose file is already missing never reached the form, so it is not a removal.
        foreach ($existing as $path => $image) {
            if (! in_array($path, $paths, true) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                $image->delete();
            }
        }

        foreach ($paths as $position => $path) {
            $image = $existing->get($path);

            if ($image instanceof ProductImage) {
                $image->update(['sort_order' => $position]);
            } else {
                $product->images()->create(['path' => $path, 'sort_order' => $position]);
            }
        }
    }
}
