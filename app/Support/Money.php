<?php

namespace App\Support;

use InvalidArgumentException;

final readonly class Money
{
    private function __construct(private int $cents) {}

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    /**
     * Parse a validated decimal string (digits, optional "." and 1-2 digits).
     */
    public static function fromDollars(string $dollars): self
    {
        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $dollars, $m) !== 1) {
            throw new InvalidArgumentException("Invalid dollar amount: {$dollars}");
        }

        $fraction = str_pad($m[2] ?? '', 2, '0');

        return new self(((int) $m[1]) * 100 + (int) $fraction);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function add(self $o): self
    {
        return new self($this->cents + $o->cents);
    }

    public function subtract(self $o): self
    {
        return new self($this->cents - $o->cents);
    }

    public function multiply(int $times): self
    {
        return new self($this->cents * $times);
    }

    public function isLessThan(self $o): bool
    {
        return $this->cents < $o->cents;
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function equals(self $o): bool
    {
        return $this->cents === $o->cents;
    }

    public function format(): string
    {
        $abs = abs($this->cents);
        $dollars = number_format(intdiv($abs, 100));
        $cents = str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);

        return ($this->cents < 0 ? '-' : '').'$'.$dollars.'.'.$cents;
    }
}
