<?php

namespace App\Payments;

use App\Support\Money;

final readonly class PaymentRequest
{
    /**
     * @param  list<PaymentLine>  $lines
     */
    public function __construct(
        public array $lines,
        public Money $deliveryFee,
        public string $description,
        public string $returnUrl,
        public string $cancelUrl,
    ) {}

    public static function single(string $name, Money $amount, string $description, string $returnUrl, string $cancelUrl): self
    {
        return new self([new PaymentLine($name, $amount, 1)], Money::zero(), $description, $returnUrl, $cancelUrl);
    }

    public function total(): Money
    {
        $total = $this->deliveryFee;

        foreach ($this->lines as $line) {
            $total = $total->add($line->unitPrice->multiply($line->quantity));
        }

        return $total;
    }
}
