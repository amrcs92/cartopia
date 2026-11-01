<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Payments\PaymentGateways;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        // Resolved here, not injected, so a test's fake registry is the one asked.
        $hosted = array_map(fn (PaymentMethod $method): string => $method->value, app(PaymentGateways::class)->available());

        return [
            'amount' => ['required', 'decimal:0,2', 'regex:/^\d{1,7}(\.\d{1,2})?$/', 'min:5', 'max:500'],
            'payment_method' => ['required', 'string', Rule::in($hosted)],
        ];
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::from($this->string('payment_method')->value());
    }

    public function amount(): Money
    {
        return Money::fromDollars((string) $this->validated('amount'));
    }
}
