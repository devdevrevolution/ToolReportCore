<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Trait;

use Toolreport\Core\Expression\Function\Formatting\ParseNumberFunction;

/**
 * Trait for math functions that need to parse formatted number strings.
 *
 * Automatically handles Argentine/Latin American number format:
 * - "45.000.000" → 45000000
 * - "1.234,56" → 1234.56
 */
trait ParsesFormattedNumber
{
    /**
     * Parse a value to float, handling formatted number strings.
     *
     * @return float|null null if cannot parse
     */
    protected function parseToFloat(mixed $value): ?float
    {
        if (is_string($value)) {
            return ParseNumberFunction::parseFormattedNumber($value);
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    /**
     * Parse a value to float or return empty string on failure.
     */
    protected function parseOrEmpty(mixed $value): float|string
    {
        $parsed = $this->parseToFloat($value);
        return $parsed !== null ? $parsed : '';
    }
}
