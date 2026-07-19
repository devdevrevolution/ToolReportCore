<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Filter;

/**
 * Format a numeric value as currency with a symbol.
 *
 * Usage: {{ value | currency(symbol, decimals, decimal_sep, thousands_sep, position) }}
 *
 * Examples:
 *   {{ price | currency("$") }}                         → "$1,234.56"
 *   {{ price | currency("€", 2, ",", ".") }}             → "€1.234,56"
 *   {{ price | currency("$", 2, ".", ",", "before") }}   → "$1,234.56"
 *   {{ price | currency("USD", 2, ".", ",", "after") }}  → "1,234.56 USD"
 */
class CurrencyFilter implements FilterInterface
{
    public function name(): string
    {
        return 'currency';
    }

    public function apply(mixed $value, array $params = []): mixed
    {
        if ($value === null) {
            return null;
        }

        $symbol = (string) ($params[0] ?? '$');
        $decimals = (int) ($params[1] ?? 2);
        $decimalSeparator = (string) ($params[2] ?? '.');
        $thousandsSeparator = (string) ($params[3] ?? ',');
        $position = (string) ($params[4] ?? 'before');

        $formatted = number_format((float) $value, $decimals, $decimalSeparator, $thousandsSeparator);

        if ($position === 'after') {
            return $formatted . ' ' . $symbol;
        }

        return $symbol . $formatted;
    }
}