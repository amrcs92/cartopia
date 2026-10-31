<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductIndexRequest extends FormRequest
{
    /**
     * Dollar strings Money::fromDollars parses, capped at 7 integer digits so
     * the cents value cannot overflow an int.
     */
    private const string DOLLARS = 'regex:/^\d{1,7}(\.\d{1,2})?$/';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'min_price' => ['nullable', 'decimal:0,2', self::DOLLARS, 'min:0'],
            'max_price' => ['nullable', 'decimal:0,2', self::DOLLARS, 'min:0', Rule::when($this->filled('min_price'), 'gte:min_price')],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'sort' => ['nullable', Rule::in(Product::SORTS)],
            'aisle' => ['nullable', 'array', 'max:20'],
            'aisle.*' => ['string', 'exists:categories,slug'],
            'rated' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The validated filters, with prices converted to cents.
     *
     * @return array{q: ?string, min_price: ?int, max_price: ?int, rating: ?int, sort: string, aisle: list<string>|null, rated: bool, featured: bool}
     */
    public function filters(): array
    {
        return [
            'q' => $this->validated('q'),
            'min_price' => $this->cents('min_price'),
            'max_price' => $this->cents('max_price'),
            'rating' => $this->validated('rating') === null ? null : (int) $this->validated('rating'),
            'sort' => $this->validated('sort') ?? 'newest',
            'aisle' => $this->aisles(),
            'rated' => $this->boolean('rated'),
            'featured' => $this->boolean('featured'),
        ];
    }

    /**
     * The raw filter inputs, for repopulating the filter controls.
     *
     * @return array{q: mixed, min_price: mixed, max_price: mixed, rating: mixed, sort: mixed, aisle: mixed, rated: mixed, featured: mixed}
     */
    public function rawFilters(): array
    {
        return [
            'q' => $this->query('q'),
            'min_price' => $this->query('min_price'),
            'max_price' => $this->query('max_price'),
            'rating' => $this->query('rating'),
            'sort' => $this->query('sort'),
            'aisle' => $this->query('aisle'),
            'rated' => $this->query('rated'),
            'featured' => $this->query('featured'),
        ];
    }

    /**
     * @return list<string>|null
     */
    private function aisles(): ?array
    {
        $slugs = $this->validated('aisle');

        return $slugs === null ? null : array_values(array_unique($slugs));
    }

    private function cents(string $key): ?int
    {
        $value = $this->validated($key);

        return $value === null ? null : Money::fromDollars((string) $value)->cents();
    }
}
