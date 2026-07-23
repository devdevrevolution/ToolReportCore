<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Formatting;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * FORMAT_CURRENCY: Format a number as currency with symbol.
 *
 * Usage: FORMAT_CURRENCY(value, symbol, decimals, dec_sep, thousands_sep, position)
 */
class FormatCurrencyFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'FORMAT_CURRENCY';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if (!is_numeric($value)) {
            return '';
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
