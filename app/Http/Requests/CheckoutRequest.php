<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Payments\PaymentGateways;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    /**
     * Deliberately no price or total fields: the server prices the cart.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Resolved here, not injected, so a test's fake registry is the one asked.
        $hosted = array_map(fn (PaymentMethod $method): string => $method->value, app(PaymentGateways::class)->available());

        return [
            'shipping_method_id' => ['required', 'integer', 'exists:shipping_methods,id'],
            'payment_method' => ['required', 'string', Rule::in([PaymentMethod::Wallet->value, ...$hosted])],
        ];
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::from($this->string('payment_method')->value());
    }
}
