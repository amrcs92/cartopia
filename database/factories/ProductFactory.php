<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = rtrim(fake()->unique()->sentence(3), '.');

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'price' => fake()->numberBetween(50, 5000),
            'sku' => fake()->unique()->numerify('##############'),
        ];
    }

    public function withImages(int $count = 1): static
    {
        return $this->afterCreating(function (Product $product) use ($count): void {
            for ($i = 0; $i < $count; $i++) {
                ProductImage::factory()->for($product)->create(['sort_order' => $i]);
            }
        });
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }

    public function hidden(): static
    {
        return $this->state(['is_visible' => false]);
    }

    public function inCategory(Category $category): static
    {
        return $this->state(['category_id' => $category->id]);
    }
}
