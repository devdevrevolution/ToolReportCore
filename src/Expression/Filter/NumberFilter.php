<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Filter;

/**
 * Format a numeric value with decimal and thousands separators.
 *
 * Usage: {{ value | number(decimals, decimal_sep, thousands_sep) }}
 *
 * Examples:
 *   {{ total | number(2) }}           → "1234.56"
 *   {{ total | number(2, ",", ".") }} → "1.234,56" (European format)
 *   {{ total | number(0) }}           → "1235"
 */
class NumberFilter implements FilterInterface
{
    public function name(): string
    {
        return 'number';
    }

    public function apply(mixed $value, array $params = []): mixed
    {
        if ($value === null) {
            return null;
        }

        $decimals = (int) ($params[0] ?? 0);
        $decimalSeparator = (string) ($params[1] ?? '.');
        $thousandsSeparator = (string) ($params[2] ?? ',');

        return number_format((float) $value, $decimals, $decimalSeparator, $thousandsSeparator);
    }
}