<?php

namespace App\Support\Money;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Immutable money value in integer rial. No floating point anywhere.
 */
final readonly class Money implements JsonSerializable
{
    private function __construct(public int $rials) {}

    public static function rials(int $amount): self
    {
        return new self($amount);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Convert user input in a display unit (e.g. 125000 toman) to rial.
     */
    public static function fromUnit(int $amount, CurrencyUnit $unit): self
    {
        return new self(self::multiply($amount, $unit->rialFactor()));
    }

    public function plus(self $other): self
    {
        $b = $other->rials;

        if (($b > 0 && $this->rials > PHP_INT_MAX - $b) || ($b < 0 && $this->rials < PHP_INT_MIN - $b)) {
            throw new InvalidArgumentException('Money overflow.');
        }

        return new self($this->rials + $b);
    }

    public function minus(self $other): self
    {
        return $this->plus(new self(-$other->rials));
    }

    public function times(int $factor): self
    {
        return new self(self::multiply($this->rials, $factor));
    }

    /**
     * Percentage of this amount, rounded half-up to whole rials. $basisPoints: 1250 = 12.5%.
     */
    public function percentage(int $basisPoints): self
    {
        return new self(intdiv($this->rials * $basisPoints + 5000, 10000));
    }

    public function isNegative(): bool
    {
        return $this->rials < 0;
    }

    public function isZero(): bool
    {
        return $this->rials === 0;
    }

    public function equals(self $other): bool
    {
        return $this->rials === $other->rials;
    }

    public function greaterThan(self $other): bool
    {
        return $this->rials > $other->rials;
    }

    public function jsonSerialize(): int
    {
        return $this->rials;
    }

    private static function multiply(int $a, int $b): int
    {
        if ($a !== 0 && $b !== 0 && abs($b) > intdiv(PHP_INT_MAX, abs($a))) {
            throw new InvalidArgumentException('Money overflow.');
        }

        return $a * $b;
    }
}
