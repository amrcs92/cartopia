<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $image_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'image_path'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * The newest product in the aisle that has a photo. Eager-load it as
     * `latestProductWithImage.primaryImage` for lists, so imageUrl() costs no
     * query per aisle.
     *
     * @return HasOne<Product, $this>
     */
    public function latestProductWithImage(): HasOne
    {
        return $this->hasOne(Product::class)
            ->ofMany(['id' => 'max'], fn (Builder $query) => $query->where('is_visible', true)->whereHas('primaryImage'));
    }

    /**
     * The aisle's own image, else the newest product photo in it, else null.
     */
    public function imageUrl(): ?string
    {
        $path = $this->image_path ?? $this->latestProductWithImage?->primaryImage?->path;

        return $path === null ? null : Storage::disk('public')->url($path);
    }
}
