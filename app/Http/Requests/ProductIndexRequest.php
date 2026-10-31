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
     * Exactly the dollar strings Money::fromDollars parses.
     */
    private const string DOLLARS = 'regex:/^\d+(\.\d{1,2})?$/';

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
        ];
    }

    /**
     * The validated filters, with prices converted to cents.
     *
     * @return array{q: ?string, min_price: ?int, max_price: ?int, rating: ?int, sort: string}
     */
    public function filters(): array
    {
        return [
            'q' => $this->validated('q'),
            'min_price' => $this->cents('min_price'),
            'max_price' => $this->cents('max_price'),
            'rating' => $this->validated('rating') === null ? null : (int) $this->validated('rating'),
            'sort' => $this->validated('sort') ?? 'newest',
        ];
    }

    /**
     * The raw filter inputs, for repopulating the filter controls.
     *
     * @return array{q: mixed, min_price: mixed, max_price: mixed, rating: mixed, sort: mixed}
     */
    public function rawFilters(): array
    {
        return [
            'q' => $this->query('q'),
            'min_price' => $this->query('min_price'),
            'max_price' => $this->query('max_price'),
            'rating' => $this->query('rating'),
            'sort' => $this->query('sort'),
        ];
    }

    private function cents(string $key): ?int
    {
        $value = $this->validated($key);

        return $value === null ? null : Money::fromDollars((string) $value)->cents();
    }
}
