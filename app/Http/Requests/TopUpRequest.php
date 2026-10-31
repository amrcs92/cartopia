<?php

namespace App\Http\Requests;

use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TopUpRequest extends FormRequest
{
    /**
     * The regex keeps every accepted value parseable by Money::fromDollars
     * (the decimal rule alone accepts ".5", "5." and "+5").
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'decimal:0,2', 'regex:/^\d{1,7}(\.\d{1,2})?$/', 'min:5', 'max:500'],
        ];
    }

    public function amount(): Money
    {
        return Money::fromDollars((string) $this->validated('amount'));
    }
}
